<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Article;
use App\Models\Comment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Utenti ────────────────────────────────────────────────
        $editor = User::create([
            'name'     => 'Marco Esposito',
            'email'    => 'm.esposito@illaboratorio.it',
            'password' => Hash::make('password123'),
            'role'     => 'editor',
            'bio'      => 'Giornalista scientifico con 10 anni di esperienza. Specializzato in AI e medicina.',
            'photo'    => 'author-1.jpg',
            'twitter'  => '@marco_esposito',
        ]);

        $author1 = User::create([
            'name'     => 'Sara Ricci',
            'email'    => 's.ricci@illaboratorio.it',
            'password' => Hash::make('password123'),
            'role'     => 'author',
            'bio'      => 'Ingegnere ambientale. Esperta di energie rinnovabili e transizione energetica.',
            'photo'    => 'author-2.jpg',
            'twitter'  => '@sara_ricci',
        ]);

        $author2 = User::create([
            'name'     => 'Elena Romano',
            'email'    => 'e.romano@illaboratorio.it',
            'password' => Hash::make('password123'),
            'role'     => 'author',
            'bio'      => 'Astrofisica di formazione. Autrice di due saggi di divulgazione scientifica.',
            'photo'    => 'author-3.jpg',
            'twitter'  => '@elena_romano',
        ]);

        // ── Articoli ──────────────────────────────────────────────
        $articles = [
            [
                'user_id'      => $editor->id,
                'title'        => "Come l'AI sta reinventando la diagnosi medica nei laboratori italiani",
                'slug'         => 'ai-diagnosi-medica-laboratori-italiani',
                'excerpt'      => 'Nuovi algoritmi di machine learning raggiungono il 94% di precisione nella diagnosi precoce del cancro al polmone.',
                'body'         => "Nel silenzio ovattato di un laboratorio al secondo piano del dipartimento di bioinformatica dell'Università Federico II di Napoli, un algoritmo sta imparando a vedere ciò che i medici più esperti faticano a distinguere.\n\nIl progetto, coordinato dal professor Antonio De Luca, ha coinvolto tre anni di ricerca e oltre 40.000 radiografie anonimizzate. Il risultato: una precisione del 94,3% nella diagnosi precoce dei noduli polmonari, contro l'84% dei radiologi nella fase iniziale di analisi.\n\nL'algoritmo non sostituisce il medico, ma funge da secondo sguardo che riduce drasticamente i falsi negativi.",
                'category'     => 'intelligenza-artificiale',
                'status'       => 'published',
                'featured'     => true,
                'cover_image'  => 'hero-placeholder.svg',
                'read_minutes' => 8,
                'views'        => 1240,
                'published_at' => now()->subDays(1),
            ],
            [
                'user_id'      => $author1->id,
                'title'        => 'Fotovoltaico organico: la startup campana che sfida il silicio',
                'slug'         => 'fotovoltaico-organico-startup-campana',
                'excerpt'      => 'Un gruppo di ricercatori di Napoli ha sviluppato celle solari stampabili su qualsiasi superficie. Efficienza record al 18%.',
                'body'         => "Un gruppo di ricercatori dell'Università Federico II ha sviluppato un nuovo tipo di cella solare organica stampabile su qualsiasi superficie flessibile.\n\nL'efficienza raggiunta, il 18%, è un record per questo tipo di tecnologia. I costi di produzione sono dimezzati rispetto al silicio tradizionale.\n\nLa startup SolarFlex, nata dallo spin-off universitario, ha già raccolto 4 milioni di euro di investimenti.",
                'category'     => 'energia',
                'status'       => 'published',
                'featured'     => false,
                'cover_image'  => 'placeholder-1.svg',
                'read_minutes' => 5,
                'views'        => 876,
                'published_at' => now()->subDays(2),
            ],
            [
                'user_id'      => $editor->id,
                'title'        => 'Il robot che opera da solo: i primi test umani al Gemelli',
                'slug'         => 'robot-chirurgia-autonoma-gemelli',
                'excerpt'      => 'Un sistema guidato da AI ha eseguito la prima appendicectomia autonoma in Italia. Il chirurgo osserva ma non interviene.',
                'body'         => "Il Policlinico Gemelli di Roma ha ospitato il primo intervento chirurgico eseguito in autonomia da un sistema robotico guidato da intelligenza artificiale.\n\nL'operazione, un'appendicectomia laparoscopica, è durata 47 minuti. Il chirurgo responsabile ha seguito l'intera procedura senza mai toccare i comandi.\n\nIl sistema, sviluppato dalla società milanese RoboSurg, utilizza una rete neurale addestrata su oltre 200.000 video di interventi chirurgici reali.",
                'category'     => 'salute',
                'status'       => 'published',
                'featured'     => false,
                'cover_image'  => 'placeholder-1.svg',
                'read_minutes' => 6,
                'views'        => 654,
                'published_at' => now()->subDays(3),
            ],
            [
                'user_id'      => $author2->id,
                'title'        => 'Il satellite italiano che monitora il clima in tempo reale',
                'slug'         => 'satellite-italiano-clima-tempo-reale',
                'excerpt'      => 'ASI ed ESA insieme: il nuovo nanosatellite in orbita da gennaio 2026.',
                'body'         => "L'Agenzia Spaziale Italiana e l'ESA hanno annunciato il lancio di ClimaSat-1, un nanosatellite da 12 kg che monitorerà i cambiamenti climatici nel bacino del Mediterraneo.\n\nIl satellite è equipaggiato con tre strumenti sviluppati interamente in Italia: un termometro a infrarossi, uno spettrometro per i gas serra, e una camera multispettrale.\n\nI dati saranno disponibili in open access per istituzioni scientifiche entro 24 ore dalla raccolta.",
                'category'     => 'spazio',
                'status'       => 'published',
                'featured'     => false,
                'cover_image'  => 'placeholder-1.svg',
                'read_minutes' => 4,
                'views'        => 432,
                'published_at' => now()->subDays(4),
            ],
            [
                'user_id'      => $editor->id,
                'title'        => 'GPT-5 e il futuro del lavoro: quali professioni italiane sopravvivono',
                'slug'         => 'gpt5-futuro-lavoro-professioni-italiane',
                'excerpt'      => 'Uno studio del Politecnico di Milano analizza 600 professioni italiane. I mestieri a rischio, quelli al sicuro.',
                'body'         => "Il Politecnico di Milano ha pubblicato il primo rapporto italiano sull'impatto dei modelli linguistici avanzati sul mercato del lavoro nazionale.\n\nLo studio individua tre categorie: professioni a rischio di automazione completa entro il 2030 (circa il 12%), quelle che si trasformeranno significativamente (il 45%), e quelle sostanzialmente al sicuro (il 43%).\n\nTra le professioni più protette figurano quelle che richiedono presenza fisica, empatia e creatività non replicabile.",
                'category'     => 'societa',
                'status'       => 'published',
                'featured'     => false,
                'cover_image'  => 'placeholder-1.svg',
                'read_minutes' => 7,
                'views'        => 989,
                'published_at' => now()->subDays(5),
            ],
            [
                'user_id'      => $author1->id,
                'title'        => "Batterie al sodio: l'alternativa italiana al litio che cambia tutto",
                'slug'         => 'batterie-sodio-alternativa-litio-italia',
                'excerpt'      => "Un team dell'Università di Bologna ha sviluppato una batteria al sodio con densità energetica record.",
                'body'         => "Il litio potrebbe non essere più il re delle batterie. Un team di ricercatori dell'Università di Bologna ha sviluppato una nuova tecnologia a base di sodio.\n\nLa batteria raggiunge una densità energetica di 280 Wh/kg, avvicinandosi alle migliori batterie agli ioni di litio, ma con un costo del 40% inferiore.\n\nIl sodio è 500 volte più abbondante del litio sulla crosta terrestre.",
                'category'     => 'energia',
                'status'       => 'published',
                'featured'     => false,
                'cover_image'  => 'placeholder-1.svg',
                'read_minutes' => 5,
                'views'        => 712,
                'published_at' => now()->subDays(6),
            ],
        ];

        foreach ($articles as $data) {
            $article = Article::create($data);

            // Aggiungi un commento di esempio al primo articolo
            if ($article->featured) {
                Comment::create([
                    'article_id' => $article->id,
                    'name'       => 'Giovanni R.',
                    'email'      => 'giovanni.r@esempio.it',
                    'body'       => 'Articolo eccellente. Lavorando in radiologia posso confermare che uno strumento del genere sarebbe rivoluzionario per noi.',
                    'status'     => 'approved',
                ]);
                Comment::create([
                    'article_id' => $article->id,
                    'name'       => 'Carla M.',
                    'email'      => 'carla.m@esempio.it',
                    'body'       => 'Interessante il punto sui dati europei vs asiatici. Spesso si sottovaluta quanto la provenienza del training set influenzi le performance.',
                    'status'     => 'approved',
                ]);
                Comment::create([
                    'article_id' => $article->id,
                    'name'       => 'Paolo T.',
                    'email'      => 'paolo.t@esempio.it',
                    'body'       => 'Quando il progetto sarà disponibile nei nostri ospedali? Sono un oncologo e seguiamo questi sviluppi con grande interesse.',
                    'status'     => 'pending',
                ]);
            }
        }

        $this->command->info('✅ Database popolato con ' . count($articles) . ' articoli e 3 utenti.');
    }
}
