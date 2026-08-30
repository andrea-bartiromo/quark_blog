<?php

namespace App\Services\Telemetry;

use InvalidArgumentException;

/**
 * Measurement Closeout (Missione 2) — contratto canonico versionato degli
 * eventi editoriali di continuità.
 *
 * Un solo posto definisce COSA può essere scritto in
 * editorial_continuity_events. Il contratto è FAIL-CLOSED: qualunque campo
 * non presente in ALLOWED_FIELDS, qualunque nome di evento fuori da
 * EVENT_NAMES, qualunque canale fuori da SOURCE_CHANNELS e qualunque tipo di
 * transizione fuori da TRANSITION_TYPES fanno fallire la validazione invece
 * di essere salvati "per sicurezza". Il motivo è privacy prima ancora che
 * pulizia: un campo sconosciuto che arriva fino al database è esattamente il
 * modo in cui un'email, un token o un URL completo finiscono in un log di
 * telemetria senza che nessuno se ne accorga.
 *
 * Il fail-closed vive QUI, sulla validazione. Il fail-SAFE vive invece in
 * EditorialContinuityRecorder, che intercetta l'eccezione: un contratto
 * violato non deve mai interrompere la navigazione pubblica. I due
 * comportamenti non sono in contraddizione — la scrittura viene rifiutata
 * (fail-closed) e l'errore viene assorbito (fail-safe).
 */
final class EditorialEventContract
{
    /**
     * Versione dello schema di payload. Da incrementare SOLO quando cambia il
     * significato di un campo esistente o ne viene rimosso uno: l'aggiunta di
     * un campo opzionale è retrocompatibile e non richiede un bump (i consumer
     * leggono per nome, mai per posizione). Le righe già scritte conservano la
     * versione con cui sono nate — vedi la colonna schema_version.
     */
    public const SCHEMA_VERSION = 1;

    /**
     * Una view di pagina articolo pubblica. È il mattone del denominatore di
     * Missione 3: "sessioni con almeno un articolo".
     */
    public const ARTICLE_VIEWED = 'article.viewed';

    /**
     * Una view di pagina Percorso pubblica.
     */
    public const PATH_VIEWED = 'path.viewed';

    /**
     * Un controllo di transizione (precedente / successivo / "Continua da
     * qui") ERA DAVVERO DISPONIBILE e renderizzato su questa pagina. È il
     * denominatore corretto di Missione 4 — non tutte le pageview, ma solo
     * quelle in cui il lettore aveva effettivamente qualcosa da cliccare.
     */
    public const TRANSITION_AVAILABLE = 'article.transition_available';

    /**
     * Un link verso un articolo era disponibile nella pagina indice di un
     * Percorso (pillar o tappa). Denominatore delle metriche "clic sul
     * pillar" / "clic sugli articoli del Percorso" di Missione 1.
     */
    public const PATH_LINK_AVAILABLE = 'path.link_available';

    /**
     * Un'iscrizione Newsletter è stata registrata durante la sessione.
     * Volutamente privo di qualunque riferimento all'iscritto: nessun
     * subscriber_id, nessuna email, nessun token — solo il fatto che una
     * sessione con una certa sorgente ha convertito.
     */
    public const NEWSLETTER_SUBSCRIBED = 'newsletter.subscribed';

    /** @var list<string> */
    public const EVENT_NAMES = [
        self::ARTICLE_VIEWED,
        self::PATH_VIEWED,
        self::TRANSITION_AVAILABLE,
        self::PATH_LINK_AVAILABLE,
        self::NEWSLETTER_SUBSCRIBED,
    ];

    public const TRANSITION_PREVIOUS = 'previous';

    public const TRANSITION_NEXT = 'next';

    public const TRANSITION_CONTINUA_DA_QUI = 'continua_da_qui';

    public const TRANSITION_PILLAR = 'pillar';

    public const TRANSITION_ARTICLE_IN_PATH = 'article_in_path';

    /** @var list<string> */
    public const TRANSITION_TYPES = [
        self::TRANSITION_PREVIOUS,
        self::TRANSITION_NEXT,
        self::TRANSITION_CONTINUA_DA_QUI,
        self::TRANSITION_PILLAR,
        self::TRANSITION_ARTICLE_IN_PATH,
    ];

    /**
     * Tassonomia sorgente allowlisted (Missione 5). Chiusa per costruzione:
     * qualunque cosa non riconosciuta diventa 'unknown', mai un valore nuovo
     * inventato a runtime dal referrer — altrimenti la colonna diventerebbe
     * un archivio di domini di terze parti, cioè un dato non necessario che
     * non abbiamo motivo di conservare.
     *
     * @var list<string>
     */
    public const SOURCE_CHANNELS = [
        'google',
        'discover',
        'facebook',
        'linkedin',
        'newsletter',
        'internal',
        'percorso',
        'direct',
        'unknown',
    ];

    public const SOURCE_UNKNOWN = 'unknown';

    /**
     * L'insieme CHIUSO dei campi che possono raggiungere il database. Ogni
     * chiave non elencata qui fa fallire validate().
     *
     * @var list<string>
     */
    public const ALLOWED_FIELDS = [
        'event_name',
        'schema_version',
        'session_key',
        'article_id',
        'target_article_id',
        'content_cluster_id',
        'transition_type',
        'source_channel',
        'context_position',
        'occurred_at',
    ];

    /**
     * Campi che DEVONO essere presenti in ogni evento, qualunque sia il nome.
     *
     * @var list<string>
     */
    public const REQUIRED_FIELDS = [
        'event_name',
        'schema_version',
        'session_key',
        'source_channel',
        'occurred_at',
    ];

    /**
     * Valida fail-closed e restituisce il payload normalizzato, pronto per
     * l'insert. Non scrive nulla: la separazione tra "cosa è lecito" e "chi
     * scrive" permette di testare il contratto senza database.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException quando il payload viola il contratto.
     */
    public static function validate(array $payload): array
    {
        $unknown = array_diff(array_keys($payload), self::ALLOWED_FIELDS);

        if ($unknown !== []) {
            // Il messaggio elenca i NOMI dei campi rifiutati, mai i loro
            // valori: se il campo rifiutato fosse 'email' o 'token', il suo
            // valore finirebbe nei log dell'applicazione — esattamente ciò
            // che il contratto esiste per impedire.
            throw new InvalidArgumentException(
                'Campi non ammessi dal contratto eventi editoriali: '.implode(', ', $unknown).'.'
            );
        }

        $missing = array_diff(self::REQUIRED_FIELDS, array_keys($payload));

        if ($missing !== []) {
            throw new InvalidArgumentException(
                'Campi obbligatori mancanti nel contratto eventi editoriali: '.implode(', ', $missing).'.'
            );
        }

        if (! in_array($payload['event_name'], self::EVENT_NAMES, true)) {
            throw new InvalidArgumentException('Nome evento non allowlisted.');
        }

        if (! in_array($payload['source_channel'], self::SOURCE_CHANNELS, true)) {
            throw new InvalidArgumentException('Canale sorgente non allowlisted.');
        }

        $transitionType = $payload['transition_type'] ?? null;

        if ($transitionType !== null && ! in_array($transitionType, self::TRANSITION_TYPES, true)) {
            throw new InvalidArgumentException('Tipo di transizione non allowlisted.');
        }

        if ((int) $payload['schema_version'] !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Versione di schema non supportata da questo contratto.');
        }

        // Un session_key deve essere esattamente un digest esadecimale
        // SHA-256. Il controllo non è cosmetico: è l'ultima barriera che
        // impedisce a un id di sessione in chiaro, a un'email o a un
        // qualunque altro identificatore diretto di essere scritto in quella
        // colonna per errore di chiamata.
        if (! is_string($payload['session_key']) || preg_match('/^[0-9a-f]{64}$/', $payload['session_key']) !== 1) {
            throw new InvalidArgumentException('session_key deve essere un digest esadecimale a 64 caratteri.');
        }

        $eventName = $payload['event_name'];

        if ($eventName === self::TRANSITION_AVAILABLE && $transitionType === null) {
            throw new InvalidArgumentException('article.transition_available richiede un transition_type.');
        }

        if ($eventName === self::TRANSITION_AVAILABLE && ($payload['target_article_id'] ?? null) === null) {
            throw new InvalidArgumentException('article.transition_available richiede un target_article_id.');
        }

        if ($eventName === self::ARTICLE_VIEWED && ($payload['article_id'] ?? null) === null) {
            throw new InvalidArgumentException('article.viewed richiede un article_id.');
        }

        if ($eventName === self::PATH_VIEWED && ($payload['content_cluster_id'] ?? null) === null) {
            throw new InvalidArgumentException('path.viewed richiede un content_cluster_id.');
        }

        return [
            'event_name' => $eventName,
            'schema_version' => self::SCHEMA_VERSION,
            'session_key' => $payload['session_key'],
            'article_id' => self::nullableId($payload['article_id'] ?? null),
            'target_article_id' => self::nullableId($payload['target_article_id'] ?? null),
            'content_cluster_id' => self::nullableId($payload['content_cluster_id'] ?? null),
            'transition_type' => $transitionType,
            'source_channel' => $payload['source_channel'],
            'context_position' => self::nullableId($payload['context_position'] ?? null),
            'occurred_at' => $payload['occurred_at'],
        ];
    }

    private static function nullableId(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
