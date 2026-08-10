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
        match ($ref['type']) {
            'article_cover_image' => Article::whereKey($ref['record_id'])->update(['cover_image' => $ref['new_value']]),
            'ad_banner_image' => Ad::whereKey($ref['record_id'])->update(['banner_image' => $ref['new_value']]),
            'user_photo' => User::whereKey($ref['record_id'])->update(['photo' => $ref['new_value']]),
            'category_image' => Category::whereKey($ref['record_id'])->update(['image' => $ref['new_value']]),
            default => throw new RuntimeException('Tipo di riferimento sconosciuto: '.$ref['type']),
        };
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
                data_set($content, $ref['json_path'], $ref['new_value']);
            }

            $page->update(['content' => $content]);
        }
    }
}
