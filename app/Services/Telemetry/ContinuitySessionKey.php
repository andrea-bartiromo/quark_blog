<?php

namespace App\Services\Telemetry;

use Illuminate\Support\Facades\Config;

/**
 * Measurement Closeout (Missione 2/5) — pseudonimo di sessione.
 *
 * Le metriche di Missione 3 e 4 sono metriche DI SESSIONE ("sessioni con
 * almeno due articoli", "transizioni disponibili poi seguite"): richiedono
 * quindi un identificatore che permetta di riconoscere due eventi della
 * stessa visita, e nient'altro. Questa classe produce esattamente quel
 * minimo.
 *
 * COSA VIENE SCRITTO: HMAC-SHA256(session_id, APP_KEY), in esadecimale.
 *
 * PERCHÉ UN HMAC E NON L'ID DI SESSIONE. L'id di sessione Laravel è già un
 * valore casuale, non un dato personale — ma è anche la credenziale che
 * autentica la sessione stessa. Scriverlo in una tabella di telemetria
 * significherebbe che chiunque possa leggere quella tabella (un backup, un
 * export, una query di debug) può impersonare le sessioni attive. L'HMAC
 * rimuove del tutto quel rischio conservando l'unica proprietà che serve alla
 * misura: due eventi della stessa sessione producono lo stesso valore.
 *
 * PERCHÉ HMAC E NON UN HASH SEMPLICE. Un SHA-256 nudo è ricalcolabile da
 * chiunque conosca l'id di sessione, quindi permetterebbe a un attaccante che
 * ottenga un id di sessione di ritrovare tutti gli eventi di quella sessione.
 * La chiave applicativa spezza quel collegamento.
 *
 * COSA NON PUÒ ESSERE DEDOTTO da questo valore: nessuna identità, nessuna
 * email, nessun IP, nessun dispositivo, nessuna correlazione tra due visite
 * separate della stessa persona (il valore cambia con la sessione, e la
 * sessione ruota con il proprio cookie).
 */
final class ContinuitySessionKey
{
    /**
     * Deriva il pseudonimo per la sessione corrente della richiesta, oppure
     * null quando non esiste una sessione utilizzabile (es. richieste
     * stateless, o console). Il chiamante deve trattare il null come "non
     * misurabile" e NON scrivere alcun evento: un valore inventato
     * renderebbe ogni richiesta senza sessione una sessione distinta,
     * gonfiando il denominatore di Missione 3.
     */
    public function forCurrentRequest(): ?string
    {
        $session = session();

        if (! $session->isStarted()) {
            return null;
        }

        $sessionId = $session->getId();

        if (! is_string($sessionId) || $sessionId === '') {
            return null;
        }

        return $this->derive($sessionId);
    }

    /**
     * Esposto separatamente perché i test devono poter verificare la
     * stabilità (stesso input → stesso output) e la non reversibilità senza
     * costruire una sessione HTTP completa.
     */
    public function derive(string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, $this->secret());
    }

    /**
     * APP_KEY come segreto. Il fallback su una stringa costante NON è un
     * segreto reale ed è deliberato: serve solo agli ambienti di test in cui
     * la chiave non è configurata, dove la proprietà che conta è la
     * determinatezza, non la segretezza. In produzione APP_KEY è sempre
     * presente (senza, Laravel non avvia nemmeno la cifratura della
     * sessione).
     */
    private function secret(): string
    {
        $key = Config::get('app.key');

        return is_string($key) && $key !== '' ? $key : 'kairus-continuity-pseudonym-fallback';
    }
}
