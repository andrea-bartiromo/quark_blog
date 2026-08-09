<?php

namespace Tests\Feature\Migrations;

use App\Models\Newsletter;
use App\Models\NewsletterOpen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Blocca la regressione del bug corretto in questa PR:
 * 2026_05_05_154122_create_newsletter_opens_table.php dichiarava
 * newsletter_id con `->constrained('newsletters')` (plurale) — una
 * tabella mai esistita (la tabella reale è 'newsletter', singolare).
 * Su MySQL/MariaDB questo fa fallire la CREATE TABLE con l'errore 1005/
 * 150 ("Foreign key constraint is incorrectly formed"), bloccando
 * `php artisan migrate` da zero. Su SQLite (usato da questa stessa
 * suite) il bug restava invisibile, perché l'esistenza della tabella
 * referenziata non viene validata di default — motivo per cui un
 * confronto testuale sul contenuto della migration non basterebbe:
 * verifica invece lo schema realmente creato, via l'introspezione
 * cross-database di Schema (getColumns()/getForeignKeys()), non una
 * string match sul file della migration.
 *
 * La correzione allinea la CREATE TABLE originale allo schema che la
 * migration successiva (2026_05_05_162036_fix_newsletter_opens_table,
 * la correzione reale già presente all'epoca) produce comunque
 * incondizionatamente due migration più tardi: nessuna foreign key su
 * newsletter_id, solo una colonna indicizzata — lo schema realmente in
 * uso in produzione oggi.
 */
class NewsletterOpensTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_newsletter_opens_table_has_no_foreign_key_on_newsletter_id(): void
    {
        // Verifica lo schema reale (introspezione via Schema), non il
        // testo della migration: è esattamente questo che su MySQL
        // avrebbe fatto fallire la CREATE TABLE.
        $foreignKeys = Schema::getForeignKeys('newsletter_opens');

        $this->assertSame(
            [],
            $foreignKeys,
            'newsletter_opens non deve avere alcuna foreign key su newsletter_id: '
            .'lo schema di produzione (vedi fix_newsletter_opens_table) non ne ha mai avuta una valida.'
        );
    }

    public function test_newsletter_opens_table_has_the_expected_nullable_indexed_newsletter_id_column(): void
    {
        $columns = collect(Schema::getColumns('newsletter_opens'))
            ->keyBy('name');

        $this->assertTrue($columns->has('newsletter_id'), 'La colonna newsletter_id deve esistere.');
        $this->assertTrue(
            (bool) $columns->get('newsletter_id')['nullable'],
            'newsletter_id deve restare nullable (invariato rispetto a prima del fix).'
        );

        $indexes = collect(Schema::getIndexes('newsletter_opens'));

        $this->assertTrue(
            $indexes->contains(fn (array $index) => in_array('newsletter_id', $index['columns'], true)),
            'newsletter_id deve restare indicizzato (invariato rispetto a prima del fix, vedi fix_newsletter_opens_table).'
        );
    }

    // Verifica comportamentale, non solo strutturale: un newsletter_id
    // che non corrisponde a nessuna riga in 'newsletter' deve poter
    // essere scritto senza errori — esattamente il caso che una foreign
    // key verso la tabella sbagliata (o verso quella giusta, se fosse
    // stata "corretta" invece che rimossa) avrebbe potuto rompere.
    public function test_a_newsletter_open_can_be_created_with_a_newsletter_id_that_does_not_reference_any_row(): void
    {
        $this->assertSame(0, Newsletter::count());

        $open = NewsletterOpen::create([
            'newsletter_id' => 999999,
            'email' => 'lettore@example.com',
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'user_agent' => 'PHPUnit',
            'opened_at' => now(),
        ]);

        $this->assertTrue($open->exists);
        $this->assertSame(999999, $open->fresh()->newsletter_id);
    }
}
