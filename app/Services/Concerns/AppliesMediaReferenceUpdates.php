<?php

namespace App\Services\Concerns;

use App\Models\Ad;
use App\Models\Article;
use App\Models\Category;
use App\Models\SpecialPage;
use App\Models\User;
use RuntimeException;

/**
 * Applica gli aggiornamenti "updatable_references" prodotti da
 * MediaReferenceService::preflight() — riscrive ogni riferimento
 * strutturato conosciuto al nuovo disk_name di un Media. Estratta da
 * MediaMoveService (dove e' nata per lo spostamento di cartella) perche'
 * MediaWebpMigrationService (FASE 6) deve riscrivere esattamente gli
 * stessi riferimenti dopo una conversione WebP: un'unica implementazione,
 * mai due definizioni che potrebbero divergere silenziosamente su quali
 * campi vengono aggiornati o come.
 *
 * Nessuna gestione di transazione qui: il chiamante e' responsabile di
 * eseguire questi metodi dentro la propria DB::transaction() e di gestire
 * compensazione/rollback in caso di fallimento successivo.
 *
 * Ogni scrittura e' qualificata sul valore old_value catturato al momento
 * del preflight (o sull'equivalente colonna DB per category_image, che
 * memorizza solo il basename — vedi expectedCurrentValue()), non solo
 * sull'ID del record: tra la lettura del preflight e questa scrittura
 * possono passare un'immagine intera da convertire e la sincronizzazione
 * della radice pubblica secondaria (molto piu' tempo del semplice rename
 * di MediaMoveService), durante il quale un redattore potrebbe aver gia'
 * cambiato quella stessa copertina/banner/foto/immagine. Un update
 * incondizionato per ID sovrascriverebbe silenziosamente quella modifica
 * piu' recente con il path appena migrato. Se il valore e' cambiato nel
 * frattempo, l'update qui sotto interessa zero righe (o, per le pagine
 * speciali, viene saltato dopo il controllo esplicito): il riferimento
 * resta quello piu' recente, non viene mai riportato indietro.
 *
 * applyReferenceUpdates() e' "protected" (non "private") esclusivamente
 * per i test: un vero fallimento di scrittura DB a meta' applicazione
 * (dopo che il WebP esiste gia' sul filesystem) non e' facilmente
 * simulabile in modo affidabile altrimenti — una sottoclasse di test che
 * sovrascrive questo solo metodo e' il modo meno invasivo per verificare
 * il rollback/compensazione, stesso pattern gia' stabilito da
 * ImageService::removeFile() e PublicMediaSyncService::removeFile().
 */
trait AppliesMediaReferenceUpdates
{
    /**
     * @param  list<array<string, mixed>>  $updatable
     */
    protected function applyReferenceUpdates(array $updatable): void
    {
        $specialPageRefs = [];

        foreach ($updatable as $ref) {
            if ($ref['type'] === 'special_page_content') {
                $specialPageRefs[] = $ref;

                continue;
            }

            $this->applySingleReference($ref);
        }

        $this->applySpecialPageReferences($specialPageRefs);
    }

    /**
     * @param  array<string, mixed>  $ref
     */
    private function applySingleReference(array $ref): void
    {
        $expectedCurrentValue = $this->expectedCurrentValue($ref);

        match ($ref['type']) {
            'article_cover_image' => Article::whereKey($ref['record_id'])->where('cover_image', $expectedCurrentValue)->update(['cover_image' => $ref['new_value']]),
            'ad_banner_image' => Ad::whereKey($ref['record_id'])->where('banner_image', $expectedCurrentValue)->update(['banner_image' => $ref['new_value']]),
            'user_photo' => User::whereKey($ref['record_id'])->where('photo', $expectedCurrentValue)->update(['photo' => $ref['new_value']]),
            'category_image' => Category::whereKey($ref['record_id'])->where('image', $expectedCurrentValue)->update(['image' => $ref['new_value']]),
            default => throw new RuntimeException('Tipo di riferimento sconosciuto: '.$ref['type']),
        };
    }

    /**
     * Valore attualmente atteso nella colonna DB per questo riferimento.
     * Uguale a old_value per tutti i tipi tranne category_image, la cui
     * colonna memorizza solo il basename (vedi
     * MediaReferenceService::scanCategoryImages()): old_value li' e'
     * invece il disk_name completo (es. "categories/pic.png"), quindi va
     * ridotto al basename per confrontarsi correttamente con la colonna.
     *
     * @param  array<string, mixed>  $ref
     */
    private function expectedCurrentValue(array $ref): string
    {
        return $ref['type'] === 'category_image' ? basename($ref['old_value']) : $ref['old_value'];
    }

    /**
     * @param  list<array<string, mixed>>  $refs
     */
    private function applySpecialPageReferences(array $refs): void
    {
        $byPage = [];
        foreach ($refs as $ref) {
            $byPage[$ref['record_id']][] = $ref;
        }

        foreach ($byPage as $pageId => $pageRefs) {
            $page = SpecialPage::whereKey($pageId)->lockForUpdate()->firstOrFail();
            $content = $page->content ?? [];

            foreach ($pageRefs as $ref) {
                // Stessa qualificazione sul valore atteso di
                // applySingleReference(), qui esplicita (non un WHERE SQL)
                // perche' il contenuto e' un JSON letto e riscritto per
                // intero: se un redattore ha gia' cambiato questo campo
                // nel frattempo, non sovrascriverlo con il path migrato.
                if (data_get($content, $ref['json_path']) !== $ref['old_value']) {
                    continue;
                }

                data_set($content, $ref['json_path'], $ref['new_value']);
            }

            $page->update(['content' => $content]);
        }
    }
}
