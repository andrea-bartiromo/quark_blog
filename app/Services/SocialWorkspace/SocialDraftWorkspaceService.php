<?php

namespace App\Services\SocialWorkspace;

use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\SocialDraft;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Servizio applicativo del Workspace Social Admin V1 — coordina
 * validazione, transizioni di stato, collisioni, timezone e audit. Non
 * conosce alcun provider esterno, non effettua chiamate di rete, non
 * scrive mai su social_publications. Il controller resta sottile: ogni
 * regola di dominio vive qui.
 */
class SocialDraftWorkspaceService
{
    /**
     * Campi bloccati in sola lettura una volta approvata la bozza, finché
     * non torna esplicitamente in revisione (Prompt 56).
     */
    private const LOCKED_WHEN_APPROVED = ['copy', 'destination_url'];

    public function __construct(
        private readonly SocialDraftStateMachine $stateMachine,
        private readonly SocialDraftUtmService $utm,
        private readonly SocialDraftCollisionDetector $collisions,
        private readonly SocialDraftScheduleTimeResolver $scheduleTime,
    ) {}

    public function create(Article $article, string $channel, ?string $copy, ?User $actor): SocialDraft
    {
        if (! array_key_exists($channel, SocialDraft::CHANNELS)) {
            throw new SocialDraftValidationException('Canale non supportato.');
        }

        $draft = SocialDraft::create([
            'article_id' => $article->id,
            'channel' => $channel,
            'status' => SocialDraft::STATUS_DRAFT,
            'copy' => $copy,
            'created_by' => $actor?->id,
        ]);

        ActivityLog::record('Bozza Social creata', 'social_draft', $draft->id, $article->title);

        return $draft;
    }

    /**
     * @param  array{copy?: ?string, destination_url?: ?string, use_utm?: bool, utm_campaign?: ?string, scheduled_date?: ?string, scheduled_time?: ?string}  $attributes
     */
    public function update(SocialDraft $draft, array $attributes): SocialDraft
    {
        $changes = [];

        if (array_key_exists('copy', $attributes)) {
            $changes['copy'] = $attributes['copy'];
        }

        if (array_key_exists('destination_url', $attributes)) {
            $url = filled($attributes['destination_url']) ? trim((string) $attributes['destination_url']) : null;

            if ($url !== null && ! $this->utm->isSafeUrl($url)) {
                throw new SocialDraftValidationException('URL di destinazione non valido: deve essere un indirizzo http/https sullo stesso dominio di Kairus.');
            }

            $changes['destination_url'] = $url;
        }

        if (array_key_exists('use_utm', $attributes)) {
            $changes['use_utm'] = (bool) $attributes['use_utm'];
        }

        if (array_key_exists('utm_campaign', $attributes)) {
            $changes['utm_campaign'] = filled($attributes['utm_campaign']) ? trim((string) $attributes['utm_campaign']) : null;
        }

        if (array_key_exists('scheduled_date', $attributes) || array_key_exists('scheduled_time', $attributes)) {
            $date = $attributes['scheduled_date'] ?? null;
            $time = $attributes['scheduled_time'] ?? null;

            try {
                $changes['scheduled_at'] = (filled($date) && filled($time))
                    ? $this->scheduleTime->toUtc($date, $time)
                    : null;
            } catch (\InvalidArgumentException $exception) {
                throw new SocialDraftValidationException($exception->getMessage());
            }
        }

        // Un campo bloccato (copy/destination_url dopo l'approvazione) è
        // sottoposto al controllo solo se il valore inviato differisce
        // davvero da quello salvato: il form HTML invia comunque i campi
        // "readonly" (a differenza di "disabled", il browser li include nel
        // payload), quindi un controllo basato solo sulla presenza della
        // chiave bloccherebbe anche un salvataggio che non tocca affatto
        // copy/URL (es. solo UTM o programmazione) mentre approvata.
        $actuallyChangedFields = array_keys(array_filter(
            $changes,
            fn ($value, $field) => ! $this->valueMatchesCurrent($draft, $field, $value),
            ARRAY_FILTER_USE_BOTH
        ));

        $this->assertEditable($draft, $actuallyChangedFields);

        $draft->update($changes);

        return $draft->fresh();
    }

    private function valueMatchesCurrent(SocialDraft $draft, string $field, mixed $value): bool
    {
        if ($field === 'scheduled_at') {
            return optional($draft->scheduled_at)->equalTo($value) ?? ($value === null);
        }

        return $draft->getAttribute($field) === $value;
    }

    public function transition(SocialDraft $draft, string $to, ?User $actor): SocialDraft
    {
        $from = $draft->status;

        if (! $this->stateMachine->canTransition($from, $to)) {
            throw new SocialDraftValidationException("Transizione non consentita: {$from} → {$to}.");
        }

        if ($to === SocialDraft::STATUS_APPROVED && blank($draft->copy)) {
            throw new SocialDraftValidationException('Il copy non può essere vuoto per approvare la bozza.');
        }

        $updates = ['status' => $to];

        if ($to === SocialDraft::STATUS_REVIEWED && $from === SocialDraft::STATUS_DRAFT) {
            $updates['reviewed_by'] = $actor?->id;
            $updates['reviewed_at'] = now();
        }

        if ($to === SocialDraft::STATUS_DRAFT) {
            $updates['reviewed_by'] = null;
            $updates['reviewed_at'] = null;
        }

        if ($to === SocialDraft::STATUS_APPROVED && $from === SocialDraft::STATUS_REVIEWED) {
            $updates['approved_by'] = $actor?->id;
            $updates['approved_at'] = now();
        }

        if ($to === SocialDraft::STATUS_REVIEWED && $from === SocialDraft::STATUS_APPROVED) {
            $updates['approved_by'] = null;
            $updates['approved_at'] = null;
        }

        // "Annulla programmazione": la data smette di avere significato
        // una volta tornati ad approved, non deve restare una data stantia
        // pronta a confondere una futura riprogrammazione.
        if ($to === SocialDraft::STATUS_APPROVED && $from === SocialDraft::STATUS_SCHEDULED) {
            $updates['scheduled_at'] = null;
        }

        if ($to === SocialDraft::STATUS_SCHEDULED) {
            // Il lock serializza il controllo collisione e la scrittura per
            // lo stesso canale+istante: senza di esso due editor che
            // programmano contemporaneamente lo stesso slot potrebbero
            // superare entrambi assertReadyForScheduling() (nessuna riga
            // "scheduled" esiste ancora per nessuno dei due) e scrivere
            // entrambi, violando l'invariante "mai una collisione" — che
            // altrimenti varrebbe solo in assenza di concorrenza. Non
            // bloccante: un editor che perde la gara riceve subito un
            // messaggio chiaro e può semplicemente riprovare, invece di
            // restare in attesa dentro una richiesta HTTP.
            $lock = Cache::lock('social-draft:schedule:'.$draft->channel.':'.$draft->scheduled_at?->timestamp, 10);

            if (! $lock->get()) {
                throw new SocialDraftValidationException('Un altro utente sta programmando una bozza per lo stesso canale e istante: riprova tra qualche secondo.');
            }

            try {
                $this->assertReadyForScheduling($draft);
                $draft->update($updates);
            } finally {
                $lock->release();
            }
        } else {
            $draft->update($updates);
        }

        ActivityLog::record("Bozza Social: {$from} → {$to}", 'social_draft', $draft->id, $draft->article?->title);

        return $draft->fresh();
    }

    public function naturalUrl(SocialDraft $draft): string
    {
        return $this->utm->resolveDestinationUrl($draft->article, $draft->destination_url);
    }

    public function previewUrl(SocialDraft $draft): string
    {
        $base = $this->naturalUrl($draft);

        return $this->utm->withUtm($base, $draft->channel, $draft->use_utm, $draft->utm_campaign, $draft->article);
    }

    public function editorialScheduledAt(SocialDraft $draft): ?Carbon
    {
        return $draft->scheduled_at ? $this->scheduleTime->toEditorialDisplay($draft->scheduled_at) : null;
    }

    private function assertEditable(SocialDraft $draft, array $fields): void
    {
        if ($draft->status === SocialDraft::STATUS_SCHEDULED) {
            throw new SocialDraftValidationException('Una bozza programmata non può essere modificata: annulla la programmazione prima.');
        }

        if ($draft->status === SocialDraft::STATUS_APPROVED) {
            foreach (self::LOCKED_WHEN_APPROVED as $locked) {
                if (in_array($locked, $fields, true)) {
                    throw new SocialDraftValidationException("Copy e URL sono di sola lettura dopo l'approvazione: riporta la bozza in revisione per modificarli.");
                }
            }
        }
    }

    private function assertReadyForScheduling(SocialDraft $draft): void
    {
        if (blank($draft->copy)) {
            throw new SocialDraftValidationException('Il copy non può essere vuoto per programmare la bozza.');
        }

        if (blank($draft->scheduled_at)) {
            throw new SocialDraftValidationException('Imposta una data e un\'ora di programmazione prima di procedere.');
        }

        $article = $draft->article;

        if (! in_array($article->status, [Article::STATUS_PUBLISHED, Article::STATUS_SCHEDULED], true)) {
            throw new SocialDraftValidationException('L\'articolo collegato non è pubblicato né programmato: non può essere programmato un post Social per esso.');
        }

        if ($draft->scheduled_at->lessThanOrEqualTo(now())) {
            throw new SocialDraftValidationException('La data di programmazione deve essere nel futuro.');
        }

        if ($article->status === Article::STATUS_SCHEDULED
            && $article->published_at
            && $draft->scheduled_at->lessThanOrEqualTo($article->published_at)) {
            throw new SocialDraftValidationException('La programmazione Social deve essere successiva alla pubblicazione programmata dell\'articolo.');
        }

        if ($this->collisions->hasCollision($draft->channel, $draft->scheduled_at, $draft->id)) {
            throw new SocialDraftValidationException('Esiste già una bozza programmata per lo stesso canale nello stesso istante: scegli un altro orario. Le collisioni non vengono mai spostate automaticamente.');
        }
    }
}
