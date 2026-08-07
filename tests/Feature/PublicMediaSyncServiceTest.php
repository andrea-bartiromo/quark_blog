<?php

namespace Tests\Feature;

use App\Services\PublicMediaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Concerns\UsesIsolatedMediaPublicRoot;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

/**
 * Copre PublicMediaSyncService in isolamento, indipendentemente dai
 * controller che la usano: la causa del bug era che i file scritti in
 * public/assets/img (dell'app Laravel) non venivano mai copiati nella
 * document root pubblica realmente servita dal dominio (es. public_html
 * su cPanel, una directory fisicamente separata). Questo servizio replica
 * create/move/delete verso quella seconda directory quando configurata.
 */
class PublicMediaSyncServiceTest extends TestCase
{
    use RefreshDatabase;
    use UsesIsolatedPublicPath;
    use UsesIsolatedMediaPublicRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedMediaPublicRoot();
        $this->tearDownIsolatedPublicPath();
        parent::tearDown();
    }

    private function service(): PublicMediaSyncService
    {
        return app(PublicMediaSyncService::class);
    }

    private function sourceFile(string $diskName, string $content = 'fake-bytes'): string
    {
        $path = public_path('assets/img/'.$diskName);
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $content);

        return $path;
    }

    public function test_sync_is_disabled_by_default_and_does_nothing(): void
    {
        // Nessuna chiamata a setUpIsolatedMediaPublicRoot(): MEDIA_PUBLIC_ROOT
        // non e' configurato, comportamento di default in ogni ambiente
        // esistente finche' non lo si imposta esplicitamente.
        $source = $this->sourceFile('foto.jpg');

        $this->assertFalse($this->service()->isEnabled());

        // Nessuna eccezione, nessuna scrittura da verificare: non c'e' una
        // radice pubblica a cui scrivere.
        $this->service()->create($source, 'foto.jpg');
        $this->service()->delete('foto.jpg');

        $this->assertTrue(true);
    }

    public function test_create_copies_the_file_into_the_configured_public_root(): void
    {
        $this->setUpIsolatedMediaPublicRoot();
        $source = $this->sourceFile('foto.jpg', 'contenuto-reale');

        $this->service()->create($source, 'foto.jpg');

        $target = $this->isolatedMediaPublicRoot.'/foto.jpg';
        $this->assertFileExists($target);
        $this->assertSame('contenuto-reale', file_get_contents($target));
    }

    // Riproduce il bug Windows: media.public_root impostato con "\" (es.
    // in .env su un sistema dove DIRECTORY_SEPARATOR e' "\") non deve
    // produrre un path di destinazione con separatori misti —
    // resolveTarget() normalizza sempre a "/". Il backslash e' letterale
    // (non DIRECTORY_SEPARATOR) cosi' il test esercita davvero la
    // normalizzazione anche quando gira su Linux, dove sarebbe altrimenti
    // un no-op.
    public function test_create_normalizes_a_mixed_separator_public_root(): void
    {
        $this->setUpIsolatedMediaPublicRoot();
        $mixedRoot = str_replace('/', '\\', $this->isolatedMediaPublicRoot);
        config(['media.public_root' => $mixedRoot]);

        $source = $this->sourceFile('foto.jpg', 'contenuto-reale');

        $this->service()->create($source, 'foto.jpg');

        $target = $this->isolatedMediaPublicRoot.'/foto.jpg';
        $this->assertFileExists($target);
        $this->assertSame('contenuto-reale', file_get_contents($target));
    }

    public function test_create_preserves_subfolders_from_the_disk_name(): void
    {
        $this->setUpIsolatedMediaPublicRoot();
        $source = $this->sourceFile('articoli/copertine/foto.jpg');

        $this->service()->create($source, 'articoli/copertine/foto.jpg');

        $this->assertFileExists($this->isolatedMediaPublicRoot.'/articoli/copertine/foto.jpg');
    }

    public function test_create_is_a_noop_when_the_public_root_resolves_to_the_same_directory_as_the_app_root(): void
    {
        // Simula un symlink impostato a livello di sistema operativo tra
        // public_html/assets/img e public/assets/img: le due radici
        // coincidono fisicamente, quindi copiare sarebbe scrivere un file
        // su se stesso.
        config(['media.public_root' => public_path('assets/img')]);

        $source = $this->sourceFile('foto.jpg');

        // isEnabled() riflette solo la presenza della config, non la
        // coincidenza dei percorsi: la verifica reale e' che create() non
        // sollevi errori (il file esiste già identico a se stesso).
        $this->assertTrue($this->service()->isEnabled());
        $this->service()->create($source, 'foto.jpg');

        $this->assertTrue(true);
    }

    public function test_create_throws_when_the_source_file_is_missing(): void
    {
        $this->setUpIsolatedMediaPublicRoot();

        $this->expectException(RuntimeException::class);

        $this->service()->create(public_path('assets/img/inesistente.jpg'), 'inesistente.jpg');
    }

    public function test_create_throws_when_a_different_file_already_exists_at_the_destination(): void
    {
        $this->setUpIsolatedMediaPublicRoot();
        $source = $this->sourceFile('foto.jpg', 'nuovo-contenuto-più-lungo');

        file_put_contents($this->isolatedMediaPublicRoot.'/foto.jpg', 'x');

        $this->expectException(RuntimeException::class);

        $this->service()->create($source, 'foto.jpg');

        // Il file preesistente non deve essere stato toccato.
        $this->assertSame('x', file_get_contents($this->isolatedMediaPublicRoot.'/foto.jpg'));
    }

    public function test_create_treats_a_same_size_but_different_content_file_as_a_collision(): void
    {
        // filesize() da solo non basta a rilevare una collisione: due file
        // diversi possono coincidere per numero di byte. Il file gia'
        // presente e quello sorgente hanno qui la stessa lunghezza ma
        // contenuto diverso, quindi deve essere rilevata una vera
        // collisione (confronto per digest SHA-256), non un falso "uguale".
        $this->setUpIsolatedMediaPublicRoot();
        $source = $this->sourceFile('foto.jpg', 'AAAAAAAAAA');

        file_put_contents($this->isolatedMediaPublicRoot.'/foto.jpg', 'BBBBBBBBBB');

        $this->expectException(RuntimeException::class);

        $this->service()->create($source, 'foto.jpg');

        $this->assertSame('BBBBBBBBBB', file_get_contents($this->isolatedMediaPublicRoot.'/foto.jpg'));
    }

    public function test_create_is_a_noop_when_the_same_content_already_exists_at_the_destination(): void
    {
        // Complemento del test sopra: quando il file gia' presente ha
        // davvero lo stesso contenuto (stesso digest), create() non deve
        // sollevare un errore di collisione — e' il caso normale di una
        // ri-sincronizzazione (es. compensazione di rollback) su un file
        // gia' correttamente presente.
        $this->setUpIsolatedMediaPublicRoot();
        $source = $this->sourceFile('foto.jpg', 'contenuto-identico');

        file_put_contents($this->isolatedMediaPublicRoot.'/foto.jpg', 'contenuto-identico');

        $this->service()->create($source, 'foto.jpg');

        $this->assertSame('contenuto-identico', file_get_contents($this->isolatedMediaPublicRoot.'/foto.jpg'));
    }

    public function test_create_reports_manual_cleanup_needed_when_an_invalid_copy_cannot_be_removed(): void
    {
        $this->setUpIsolatedMediaPublicRoot();
        $source = $this->sourceFile('foto.jpg', 'contenuto');

        // sameContent() forzato a restituire sempre false: simula una
        // copia risultata non valida in fase di verifica (una copy() reale
        // e deterministica non produce mai un mismatch dopo un successo).
        // removeFile() forzato a restituire false: simula un vero
        // fallimento di unlink() sulla copia non valida, che a questo
        // punto e' un file realmente presente nella radice pubblica.
        $service = new class extends PublicMediaSyncService
        {
            protected function sameContent(string $pathA, string $pathB): bool
            {
                return false;
            }

            protected function removeFile(string $path): bool
            {
                return false;
            }
        };

        try {
            $service->create($source, 'foto.jpg');
            $this->fail('Doveva segnalare la necessita\' di una pulizia manuale.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('pulizia manuale', $exception->getMessage());
        }

        // La copia (non valida ma comunque scritta da copy()) resta sul
        // disco: la rimozione e' fallita, quindi non deve essere data per
        // scontata una pulizia che non e' davvero avvenuta.
        $this->assertFileExists($this->isolatedMediaPublicRoot.'/foto.jpg');
    }

    public function test_delete_removes_the_file_from_the_public_root(): void
    {
        $this->setUpIsolatedMediaPublicRoot();
        $source = $this->sourceFile('foto.jpg');
        $this->service()->create($source, 'foto.jpg');

        $this->service()->delete('foto.jpg');

        $this->assertFileDoesNotExist($this->isolatedMediaPublicRoot.'/foto.jpg');
    }

    public function test_delete_is_idempotent_when_the_file_was_never_synced(): void
    {
        $this->setUpIsolatedMediaPublicRoot();

        // Nessuna eccezione attesa: puo' capitare per un file caricato
        // prima dell'attivazione della sincronizzazione.
        $this->service()->delete('mai-sincronizzato.jpg');

        $this->assertTrue(true);
    }

    public function test_move_creates_at_the_new_location_and_removes_the_old_one(): void
    {
        $this->setUpIsolatedMediaPublicRoot();
        $this->sourceFile('foto.jpg', 'contenuto');
        $this->service()->create(public_path('assets/img/foto.jpg'), 'foto.jpg');

        // Chi chiama move() lo fa dopo aver gia' rinominato il file
        // applicativo: replichiamo qui la stessa precondizione.
        @mkdir(public_path('assets/img/archivio'), 0775, true);
        rename(public_path('assets/img/foto.jpg'), public_path('assets/img/archivio/foto.jpg'));

        $this->service()->move(public_path('assets/img/archivio/foto.jpg'), 'foto.jpg', 'archivio/foto.jpg');

        $this->assertFileExists($this->isolatedMediaPublicRoot.'/archivio/foto.jpg');
        $this->assertFileDoesNotExist($this->isolatedMediaPublicRoot.'/foto.jpg');
    }

    public function test_move_self_heals_a_file_that_was_never_previously_synced(): void
    {
        $this->setUpIsolatedMediaPublicRoot();
        $this->sourceFile('archivio/foto.jpg', 'contenuto');

        // Nessuna create() precedente: il file non e' mai stato
        // sincronizzato (es. caricato prima di MEDIA_PUBLIC_ROOT).
        $this->service()->move(public_path('assets/img/archivio/foto.jpg'), 'foto.jpg', 'archivio/foto.jpg');

        $this->assertFileExists($this->isolatedMediaPublicRoot.'/archivio/foto.jpg');
    }

    public function test_move_removes_the_newly_created_copy_when_deleting_the_old_one_fails(): void
    {
        $this->setUpIsolatedMediaPublicRoot();
        $this->sourceFile('archivio/foto.jpg', 'contenuto');

        // Un vero fallimento di delete() del vecchio nome (a differenza dei
        // permessi, non simulabile in modo affidabile mentre il processo
        // gira come root) viene simulato con una sottoclasse che fa
        // fallire solo delete(), lasciando create() invariato: replica
        // esattamente lo scenario "create(new) riuscita, delete(old)
        // fallita" che move() deve compensare.
        $service = new class extends PublicMediaSyncService
        {
            public function delete(string $diskName): void
            {
                throw new RuntimeException('simulazione: delete del vecchio nome fallita');
            }
        };

        try {
            $service->move(public_path('assets/img/archivio/foto.jpg'), 'foto.jpg', 'archivio/foto.jpg');
            $this->fail('Doveva rilanciare l\'eccezione di delete().');
        } catch (RuntimeException) {
            // atteso
        }

        // La copia creata da questa stessa move() (mai esistita prima)
        // viene rimossa: nessuna copia duplicata e non referenziata deve
        // restare nella radice pubblica.
        $this->assertFileDoesNotExist($this->isolatedMediaPublicRoot.'/archivio/foto.jpg');
    }

    public function test_move_does_not_touch_a_preexisting_copy_when_deleting_the_old_one_fails(): void
    {
        $this->setUpIsolatedMediaPublicRoot();
        $this->sourceFile('archivio/foto.jpg', 'contenuto');

        // La destinazione ha gia' lo stesso contenuto, come dopo una
        // precedente sincronizzazione riuscita: create() la trova identica
        // e non la "crea" nel senso che conta per la compensazione, quindi
        // un fallimento della successiva delete() non deve toccarla.
        mkdir($this->isolatedMediaPublicRoot.'/archivio', 0775, true);
        file_put_contents($this->isolatedMediaPublicRoot.'/archivio/foto.jpg', 'contenuto');

        $service = new class extends PublicMediaSyncService
        {
            public function delete(string $diskName): void
            {
                throw new RuntimeException('simulazione: delete del vecchio nome fallita');
            }
        };

        try {
            $service->move(public_path('assets/img/archivio/foto.jpg'), 'foto.jpg', 'archivio/foto.jpg');
            $this->fail('Doveva rilanciare l\'eccezione di delete().');
        } catch (RuntimeException) {
            // atteso
        }

        $this->assertFileExists($this->isolatedMediaPublicRoot.'/archivio/foto.jpg');
        $this->assertSame('contenuto', file_get_contents($this->isolatedMediaPublicRoot.'/archivio/foto.jpg'));
    }

    public function test_public_root_directory_is_created_when_missing(): void
    {
        $root = sys_get_temp_dir().'/kairus-test-media-public-root-'.uniqid('', true);
        config(['media.public_root' => $root]);
        $source = $this->sourceFile('foto.jpg');

        $this->assertDirectoryDoesNotExist($root);

        $this->service()->create($source, 'foto.jpg');

        $this->assertFileExists($root.'/foto.jpg');

        $this->deleteMediaPublicRootRecursivelyForCleanup($root);
    }

    public function test_cleanup_after_failed_create_removes_the_local_file(): void
    {
        $source = $this->sourceFile('foto.jpg', 'contenuto-mai-pubblicato');

        $this->assertFileExists($source);

        $this->service()->cleanupAfterFailedCreate($source);

        $this->assertFileDoesNotExist($source);
    }

    public function test_cleanup_after_failed_create_is_a_noop_when_there_is_nothing_to_remove(): void
    {
        // Nessuna eccezione attesa: puo' capitare se create() e' fallita
        // prima ancora di scrivere qualcosa (es. file sorgente mancante).
        $this->service()->cleanupAfterFailedCreate(public_path('assets/img/mai-esistito.jpg'));

        $this->assertTrue(true);
    }

    public function test_cleanup_after_failed_create_logs_a_warning_when_the_local_unlink_fails(): void
    {
        // Un vero fallimento di unlink() (a differenza del caso "niente da
        // rimuovere" sopra) deve essere registrato: qui lo simuliamo
        // sostituendo il file con una directory allo stesso path, cosi'
        // is_file() lo vede ancora come presente ma @unlink() fallisce
        // davvero, indipendentemente dai privilegi del processo.
        $path = public_path('assets/img/bloccata.jpg');
        mkdir($path, 0775, true);

        Log::spy();

        $this->service()->cleanupAfterFailedCreate($path);

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context) => $context['operation'] === 'cleanup_after_failed_create'
                && $context['path'] === $path
        );

        rmdir($path);
    }

    private function deleteMediaPublicRootRecursivelyForCleanup(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            is_dir($path) ? $this->deleteMediaPublicRootRecursivelyForCleanup($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
