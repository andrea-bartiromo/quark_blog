<?php

namespace Database\Seeders;

use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Crea il template "Newsletter settimanale Kairus" con testo generico
 * riutilizzabile — nessun dato reale importato. Idempotente: rieseguirlo
 * non crea duplicati.
 *
 * Non viene mai chiamato da DatabaseSeeder::run(). Va eseguito solo
 * esplicitamente con: php artisan db:seed --class=CommunicationTemplateSeeder
 */
class CommunicationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        if (CommunicationTemplate::where('name', 'Newsletter settimanale Kairus')->exists()) {
            $this->command?->info('Template "Newsletter settimanale Kairus" già presente, nessuna azione.');

            return;
        }

        $editor = User::where('role', 'editor')->first();

        DB::transaction(function () use ($editor) {
            $template = CommunicationTemplate::create([
                'name' => 'Newsletter settimanale Kairus',
                'description' => 'Struttura di base per la newsletter settimanale: apertura, rassegna articoli, chiusura.',
                'type' => 'newsletter',
                'status' => CommunicationTemplate::STATUS_ACTIVE,
                'created_by' => $editor?->id,
                'updated_by' => $editor?->id,
            ]);

            $version = CommunicationTemplateVersion::create([
                'template_id' => $template->id,
                'version_number' => 1,
                'subject' => 'Le novità della settimana da Kairus',
                'preheader' => 'Un riassunto veloce di quello che è successo',
                'content' => [
                    'body' => "Ciao,\n\necco una selezione degli articoli più interessanti di questa settimana.\n\n— Il team Kairus",
                ],
                'created_by' => $editor?->id,
                'created_at' => now(),
            ]);

            $template->update(['active_version_id' => $version->id]);
        });

        $this->command?->info('✅ Template "Newsletter settimanale Kairus" creato.');
    }
}
