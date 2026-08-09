<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\ArticleLinkSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Caratterizza un falso positivo trovato con la revisione red-team di #142
 * (Ago 2026): "suoi"/"sue" (plurale di "suo"/"sua", che ERANO già in
 * STOPWORDS) non erano mai stati aggiunti — un'omissione sistematica di
 * tutti i plurali dei pronomi possessivi italiani (miei/mie, tuoi/tue,
 * suoi/sue, nostri/nostre, vostri/vostre). Combinato con sostantivi
 * meta-linguistici generici mai stopwordati ("termine", "quantità"),
 * permetteva a due articoli SENZA alcuna relazione tematica reale
 * (categorie diverse, vocabolario di dominio zero in comune) di raggiungere
 * uno score >= soglia solo condividendo parole vuote di segnale tematico.
 *
 * File isolato, corpus dedicato di sole 2 righe: la corpus condivisa di
 * InternalLinkingQualityTest.php partecipa al segnale di document-frequency
 * di OGNI test in quel file (analyzeForSource() interroga tutti gli
 * articoli pubblicati) — aggiungere righe lì avrebbe diluito il
 * denominatore e fatto scattare una regressione en passant su
 * test_generic_tech_phrasing_alone_is_not_enough_to_suggest_unrelated_topics
 * (osservato empiricamente durante questa stessa missione). Tenerlo qui
 * evita quell'accoppiamento fragile tra test scorrelati.
 */
class InternalLinkingPossessivePronounStopwordTest extends TestCase
{
    use RefreshDatabase;

    public function test_unrelated_articles_sharing_only_generic_words_are_not_suggested(): void
    {
        $service = app(ArticleLinkSuggestionService::class);
        $author = User::factory()->create(['role' => 'editor']);

        $mk = fn (string $title, string $category, string $body) => Article::create([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.uniqid(),
            'excerpt' => null,
            'body' => $body,
            'category' => $category,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'read_minutes' => 4,
            'verification_status' => 'unverified',
        ]);

        $meteoriti = $mk('Meteoriti: quando lo spazio arriva sulla Terra', 'spazio',
            '<p>I meteoriti sono frammenti rocciosi o metallici che sopravvivono all\'attraversamento '.
            'dell\'atmosfera. La loro composizione racconta molto sull\'origine del sistema solare, e la '.
            'quantità di materiale che raggiunge la superficie terrestre ogni anno è più alta di quanto '.
            'si pensi comunemente.</p><p>Gli scienziati planetari studiano questi campioni per capire '.
            'meglio la storia primordiale del nostro sistema, un termine con cui si indica l\'insieme di '.
            'Sole, pianeti e corpi minori nato dallo stesso disco protoplanetario, la cui composizione '.
            'conserva ancora oggi i suoi elementi originari.</p>');

        $socialMedia = $mk('Perché i social media catturano così tanta attenzione', 'societa',
            '<p>I social media sono progettati per massimizzare il tempo di permanenza degli utenti, '.
            'sfruttando meccanismi di ricompensa variabile simili a quelli delle slot machine. La '.
            'quantità di notifiche che riceviamo ogni giorno è pensata per riportarci costantemente '.
            'sull\'app.</p><p>Gli sviluppatori chiamano questo approccio, con un termine ormai comune '.
            'nel settore, "engagement design": ogni funzione è pensata per aumentare i suoi indicatori '.
            'di utilizzo, non necessariamente il benessere di chi la usa.</p>');

        $fromMeteoriti = $service->analyzeForSource($meteoriti->fresh());
        $fromSocialMedia = $service->analyzeForSource($socialMedia->fresh());

        $this->assertNull($fromMeteoriti->firstWhere('target_article_id', $socialMedia->id));
        $this->assertNull($fromSocialMedia->firstWhere('target_article_id', $meteoriti->id));
    }
}
