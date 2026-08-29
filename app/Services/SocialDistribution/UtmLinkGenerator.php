<?php

namespace App\Services\SocialDistribution;

use App\Models\Article;
use InvalidArgumentException;

/**
 * Growth S3 — Social Distribution Foundation. Genera link con UTM per la
 * distribuzione UFFICIALE sui social (es. un post pubblicato dall'account
 * Facebook di Kairus), mai per la condivisione organica di un lettore —
 * quella resta volutamente pulita (vedi
 * resources/views/articles/partials/share-card.blade.php, non toccato da
 * questa missione: X/WhatsApp/LinkedIn/Copia link non hanno mai portato
 * parametri UTM, e devono continuare a non averne. Confondere le due cose
 * attribuirebbe erroneamente in analytics una condivisione spontanea a una
 * campagna ufficiale mai esistita.
 *
 * v2 (Mission 8): tre canali social nominati (Facebook, X, LinkedIn) più
 * un canale "altro" per una distribuzione ufficiale non legata a una
 * piattaforma social specifica (es. un partner, una newsletter di terzi,
 * un QR code su materiale stampato) — mai per l'auto-pubblicazione: questo
 * servizio genera solo URL, non chiama alcuna API social, non richiede né
 * memorizza token/OAuth di alcuna piattaforma. L'architettura v1 era già
 * pensata per questa estensione: ogni canale resta solo una entry nella
 * mappa readonly CHANNELS, nessun contratto riscritto.
 *
 * Nessuna persistenza: stateless per design, coerente con "utility
 * opzionale" del brief — non introduce un nuovo concetto di "campagna"
 * DB-backed che dovrebbe convivere con CommunicationCampaign (dominio
 * newsletter, con la propria identità UUID/titolo) senza sovrapporsi.
 */
class UtmLinkGenerator
{
    public const CHANNEL_FACEBOOK = 'facebook';

    public const CHANNEL_X = 'x';

    public const CHANNEL_LINKEDIN = 'linkedin';

    public const CHANNEL_OTHER = 'other';

    /**
     * @var array<string, string> canale => utm_source
     */
    private const CHANNELS = [
        self::CHANNEL_FACEBOOK => 'facebook',
        self::CHANNEL_X => 'x',
        self::CHANNEL_LINKEDIN => 'linkedin',
        self::CHANNEL_OTHER => 'altro',
    ];

    /**
     * Prefisso del nome campagna auto-generato quando il redattore non ne
     * specifica uno — un valore per canale così il default resta
     * decifrabile in un report analytics (vedi normalizeCampaign()).
     *
     * @var array<string, string> canale => prefisso
     */
    private const CAMPAIGN_PREFIXES = [
        self::CHANNEL_FACEBOOK => 'fb',
        self::CHANNEL_X => 'x',
        self::CHANNEL_LINKEDIN => 'li',
        self::CHANNEL_OTHER => 'altro',
    ];

    private const UTM_MEDIUM = 'social';

    /**
     * Slug ASCII: minuscolo, cifre, trattino singolo — stesso alfabeto
     * "sicuro" già richiesto per gli slug di Article/ContentCluster in
     * questo progetto. Scarta di proposito qualunque carattere fuori da
     * questo set (spazi, accenti, punteggiatura): un campo compilato da un
     * redattore non deve poter portare per errore un nome o un indirizzo
     * email incollato per sbaglio nel parametro pubblico utm_campaign.
     */
    private const CAMPAIGN_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    private const MAX_CAMPAIGN_LENGTH = 100;

    public function forArticle(Article $article, string $channel, ?string $campaign = null): string
    {
        if (! array_key_exists($channel, self::CHANNELS)) {
            throw new InvalidArgumentException("Canale social non supportato: {$channel}.");
        }

        $campaignSlug = $this->normalizeCampaign($campaign, $article, $channel);

        // route() con l'array dei parametri query: Laravel li aggiunge alla
        // querystring, mai al path — l'URL canonico dell'articolo
        // (Article::metaCanonicalUrl()) non usa mai questa rotta con
        // parametri extra, quindi il canonical resta sempre pulito
        // indipendentemente da questo link (verificato anche da test
        // dedicati preesistenti: ArchivePaginationCanonicalTest,
        // OrganicGrowthSeoRegressionTest — un utm_* in query non finisce
        // mai nel canonical).
        return route('articolo', [
            'slug' => $article->slug,
            'utm_source' => self::CHANNELS[$channel],
            'utm_medium' => self::UTM_MEDIUM,
            'utm_campaign' => $campaignSlug,
        ]);
    }

    /**
     * Convenzione di naming: {prefisso canale}-{slug articolo}-{YYYYMMDD}
     * se il redattore non specifica nulla — sempre univoca per
     * articolo+giorno+canale, mai vuota, decifrabile in un report
     * analytics senza dover consultare altrove "a cosa si riferiva questa
     * campagna". Un'etichetta esplicita ha comunque priorità quando
     * fornita (es. per raggruppare più articoli sotto un'unica spinta
     * editoriale), purché rispetti lo stesso alfabeto slug.
     */
    private function normalizeCampaign(?string $campaign, Article $article, string $channel): string
    {
        $value = $campaign !== null && trim($campaign) !== ''
            ? trim($campaign)
            : $this->generatedCampaign($article, $channel);

        if (mb_strlen($value) > self::MAX_CAMPAIGN_LENGTH) {
            throw new InvalidArgumentException('Nome campagna troppo lungo (max '.self::MAX_CAMPAIGN_LENGTH.' caratteri).');
        }

        if (preg_match(self::CAMPAIGN_PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('Nome campagna non valido: solo lettere minuscole, cifre e trattini singoli (es. "lancio-fisica-2026").');
        }

        return $value;
    }

    private function generatedCampaign(Article $article, string $channel): string
    {
        $prefix = self::CAMPAIGN_PREFIXES[$channel];
        $date = now()->format('Ymd');
        $campaign = $prefix.'-'.$article->slug.'-'.$date;

        if (mb_strlen($campaign) <= self::MAX_CAMPAIGN_LENGTH) {
            return $campaign;
        }

        $hash = substr(hash('sha256', $article->id.'|'.$article->slug), 0, 12);
        $fixedLength = mb_strlen($prefix)+mb_strlen($hash)+mb_strlen($date)+3;
        $slug = rtrim(mb_substr($article->slug, 0, self::MAX_CAMPAIGN_LENGTH-$fixedLength), '-');

        return $prefix.'-'.$slug.'-'.$hash.'-'.$date;
    }

    /**
     * @return array<string, string> canale => etichetta leggibile
     */
    public static function channelOptions(): array
    {
        return [
            self::CHANNEL_FACEBOOK => 'Facebook',
            self::CHANNEL_X => 'X (Twitter)',
            self::CHANNEL_LINKEDIN => 'LinkedIn',
            self::CHANNEL_OTHER => 'Altro (specificare nel nome campagna)',
        ];
    }
}
