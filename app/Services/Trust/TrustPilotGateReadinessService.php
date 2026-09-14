<?php

namespace App\Services\Trust;

use App\Models\TrustKnowledgeStatement;

/**
 * Cantiere 42 (programma "100 cantieri Kairus", dipende dai Cantieri 40-41).
 *
 * Calcola e mostra SOLO lo stato delle tre condizioni del NO-GO B-45
 * (docs/TRUST_LAYER_COSA_SAPPIAMO_DAVVERO_PILOT.md) — non le imposta, non
 * le può impostare: nessun metodo qui scrive alcunché. Decisione di scope
 * portata all'utente via AskUserQuestion e confermata esplicitamente
 * ("Solo readiness read-only"): l'assegnazione di un owner editoriale e
 * l'approvazione di contenuto reale restano esplicitamente fuori da questo
 * cantiere e riservate a un cantiere successivo (44, "Admin decisione
 * GO/NO-GO pilot") che richiederà a sua volta una decisione umana esplicita
 * prima di introdurre qualunque meccanismo di scrittura.
 *
 * Le tre condizioni, nell'ordine in cui compaiono nel documento sorgente:
 * 1. Owner editoriale assegnato — nessun campo/meccanismo di assegnazione
 *    esiste ancora nel sistema per questo pilot: non "falso", ma "non
 *    ancora determinabile automaticamente" (onesto sulla differenza).
 * 2. Contenuto sorgente reale approvato — nessuna riga di
 *    TrustKnowledgeStatement esiste ancora ovunque (nessun seeder/factory
 *    la crea); anche quando esisteranno righe, questo modello non ha oggi
 *    un campo "approvato" (si legga il suo docblock) — quindi anche una
 *    riga presente non basterebbe da sola a dichiarare la condizione
 *    soddisfatta, solo a smentire "zero contenuto".
 * 3. Gate Trust Layer (componente Fonti pubblico) — già soddisfatto
 *    altrove su main (Addendum Cantiere 38): verificato qui controllando
 *    che il componente esista davvero nel codebase, non un booleano fisso.
 */
class TrustPilotGateReadinessService
{
    public const STATE_MET = 'met';

    public const STATE_NOT_MET = 'not_met';

    public const STATE_NOT_DETERMINABLE = 'not_determinable';

    /** @return array<int, array{key: string, label: string, state: string, detail: string}> */
    public function assess(): array
    {
        $realContentCount = TrustKnowledgeStatement::query()->count();

        return [
            [
                'key' => 'owner_assegnato',
                'label' => 'Owner editoriale assegnato',
                'state' => self::STATE_NOT_DETERMINABLE,
                'detail' => 'Nessun campo o meccanismo di assegnazione owner esiste ancora nel sistema per questo pilot: non verificabile automaticamente, richiede una decisione umana esplicita (fuori da questo cantiere).',
            ],
            [
                'key' => 'contenuto_approvato',
                'label' => 'Contenuto sorgente reale approvato',
                'state' => $realContentCount > 0 ? self::STATE_NOT_DETERMINABLE : self::STATE_NOT_MET,
                'detail' => $realContentCount > 0
                    ? "{$realContentCount} voce/i \"Cosa sappiamo davvero\" presente/i, ma il modello non ha ancora un campo \"approvato\": la sola presenza non basta a dichiarare la condizione soddisfatta."
                    : 'Nessuna voce "Cosa sappiamo davvero" esiste ancora in alcun ambiente: condizione certamente non soddisfatta.',
            ],
            [
                'key' => 'componente_fonti_mergiato',
                'label' => 'Componente Fonti pubblico disponibile',
                'state' => $this->primarySourcesComponentExists() ? self::STATE_MET : self::STATE_NOT_MET,
                'detail' => $this->primarySourcesComponentExists()
                    ? 'resources/views/components/article/primary-sources.blade.php esiste nel codebase: il componente Fonti pubblico è disponibile (Addendum Cantiere 38).'
                    : 'resources/views/components/article/primary-sources.blade.php non trovato nel codebase.',
            ],
        ];
    }

    public function allConditionsMet(): bool
    {
        return collect($this->assess())->every(fn (array $condition) => $condition['state'] === self::STATE_MET);
    }

    private function primarySourcesComponentExists(): bool
    {
        return is_file(resource_path('views/components/article/primary-sources.blade.php'));
    }
}
