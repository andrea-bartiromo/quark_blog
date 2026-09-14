<?php

namespace App\Console\Commands;

use App\Models\ContentCluster;
use Illuminate\Console\Command;

/**
 * Cantiere 46 (programma "100 cantieri Kairus"): provisioning di un
 * "pacchetto editoriale non pubblico" — un Percorso (ContentCluster)
 * appena creato, sempre `is_active=false`, senza articoli reali
 * collegati. Distinto deliberatamente da `content-clusters:backfill-initial`
 * (Cantiere P0): quel comando raggruppa articoli GIÀ pubblicati in un
 * Percorso a partire da una mappatura versionata
 * (config/content-clusters-initial.php) — questo comando prepara invece
 * il contenitore vuoto PRIMA che esista un solo articolo reale da
 * collegarci, per un tema editoriale ancora da scrivere (Cantiere 46
 * "Mente e comportamento", Cantiere 51 "Scienza e metodo" — stesso
 * comando, riusato).
 *
 * Nessun contenuto editoriale viene mai generato qui: solo nome e slug,
 * entrambi passati esplicitamente come argomento — mai un valore inventato
 * da questo comando. `is_active=false` (l'unica source of truth per la
 * visibilità pubblica, si legga il docblock di
 * ContentCluster::scopePubliclyVisible()) garantisce che il pacchetto
 * resti escluso da sitemap, ricerca interna e ogni superficie pubblica
 * fin dalla creazione — verificato in
 * tests/Feature/Console/ProvisionNonPublicContentClusterPackageTest.php.
 *
 * Stesso pattern di sicurezza di BackfillInitialContentClusters: dry-run
 * per default, scrittura solo con --apply esplicito, idempotente (una
 * seconda esecuzione su uno slug già esistente non lo modifica).
 */
class ProvisionNonPublicContentClusterPackage extends Command
{
    protected $signature = 'content-clusters:provision-non-public-package {slug} {name} {--apply : Apply the creation; default is dry-run}';

    protected $description = 'Plan or safely create an empty, non-public Content Cluster package (no articles, is_active=false) ready for future editorial content';

    public function handle(): int
    {
        $slug = $this->argument('slug');
        $name = $this->argument('name');
        $apply = (bool) $this->option('apply');

        $this->components->info($apply ? 'APPLY mode' : 'DRY RUN — no database writes');

        $existing = ContentCluster::query()->where('slug', $slug)->first();

        if ($existing) {
            $this->line("SKIP {$slug} — già esistente (nessuna modifica: questo comando non tocca mai un Percorso già creato)");

            return self::SUCCESS;
        }

        $this->line("CREATE NON-PUBLIC PACKAGE {$slug} ({$name})");

        if (! $apply) {
            $this->newLine();
            $this->components->info('Dry run complete. Re-run con --apply per creare davvero il pacchetto.');

            return self::SUCCESS;
        }

        ContentCluster::query()->create([
            'name' => $name,
            'slug' => $slug,
            'is_active' => false,
            'sort_order' => 0,
        ]);

        $this->newLine();
        $this->components->info("Pacchetto '{$slug}' creato, non pubblico (is_active=false, zero articoli collegati).");

        return self::SUCCESS;
    }
}
