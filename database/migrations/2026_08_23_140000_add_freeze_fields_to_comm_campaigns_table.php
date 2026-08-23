<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comm_campaigns', function (Blueprint $table) {
            // Congelamento (Recipient Snapshot + Campaign Freeze): quando
            // valorizzato, contenuto/template/mittente della campagna e
            // l'elenco destinatari (comm_sends) diventano immutabili — vedi
            // CampaignFreezeService. Deliberatamente ortogonale allo `status`
            // esistente: una campagna 'draft' o 'scheduled' può essere
            // congelata senza cambiare stato, la state machine di invio
            // resta quella già presente in CampaignStateMachine.
            $table->timestamp('frozen_at')->nullable()->after('completed_at');
            $table->foreignId('frozen_by')->nullable()->after('frozen_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('comm_campaigns', function (Blueprint $table) {
            $table->dropForeign(['frozen_by']);
            $table->dropColumn(['frozen_at', 'frozen_by']);
        });
    }
};
