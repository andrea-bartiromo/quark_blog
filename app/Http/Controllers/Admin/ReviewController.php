<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ReviewController extends Controller
{
    public function index()
    {
        $articles = Article::where('status', 'review')
            ->with('author')
            ->orderByDesc('updated_at')
            ->get();

        return view('admin.review', compact('articles'));
    }

    public function approve(Article $article)
    {
        $article->update([
            'status'       => 'published',
            'published_at' => now(),
        ]);

        $this->notifyAuthor($article, 'approved');
        ActivityLog::record('Articolo approvato', 'article', $article->id, $article->title);

        return redirect()->route('admin.review')
            ->with('success', "Articolo \"{$article->title}\" pubblicato.");
    }

    public function reject(Request $request, Article $article)
    {
        $request->validate(['note' => 'nullable|max:500']);

        $article->update([
            'status'               => 'draft',
            'verification_notes'   => $request->input('note'),
        ]);

        $this->notifyAuthor($article, 'rejected', $request->input('note'));
        ActivityLog::record('Articolo rifiutato', 'article', $article->id, $article->title);

        return redirect()->route('admin.review')
            ->with('success', "Articolo rimandato in bozza con nota.");
    }

    private function notifyAuthor(Article $article, string $status, string $note = null): void
    {
        try {
            $authorEmail = $article->author->email;
            $authorName  = $article->author->name;

            if ($status === 'approved') {
                $subject = '🎉 Il tuo articolo è stato pubblicato su Quark!';
                $body = "
                    <div style='font-family:Arial,sans-serif;max-width:540px;padding:1.5rem;'>
                        <h2 style='color:#0d9488;'>Il tuo articolo è online! 🎉</h2>
                        <p style='color:#374151;'>Ciao {$authorName},</p>
                        <p style='color:#374151;'>Il tuo articolo <strong>" . htmlspecialchars($article->title) . "</strong>
                        è stato revisionato e pubblicato su Quark.</p>
                        <a href='" . route('articolo', $article->slug) . "'
                           style='display:inline-block;background:#0d9488;color:#fff;
                                  padding:.65rem 1.25rem;border-radius:6px;text-decoration:none;font-weight:600;'>
                            Leggi l'articolo →
                        </a>
                    </div>
                ";
            } else {
                $subject = '✏️ Il tuo articolo richiede delle modifiche';
                $noteHtml = $note
                    ? "<div style='background:#fef9c3;border-radius:8px;padding:1rem;margin:1rem 0;'>
                         <strong>Nota dell'editor:</strong><br>" . htmlspecialchars($note) . "</div>"
                    : '';
                $body = "
                    <div style='font-family:Arial,sans-serif;max-width:540px;padding:1.5rem;'>
                        <h2 style='color:#f97316;'>Articolo da rivedere</h2>
                        <p style='color:#374151;'>Ciao {$authorName},</p>
                        <p style='color:#374151;'>Il tuo articolo <strong>" . htmlspecialchars($article->title) . "</strong>
                        è stato rimandato in bozza. Effettua le modifiche richieste e rinvialo in revisione.</p>
                        {$noteHtml}
                        <a href='" . route('redazione.articles.edit', $article) . "'
                           style='display:inline-block;background:#f97316;color:#fff;
                                  padding:.65rem 1.25rem;border-radius:6px;text-decoration:none;font-weight:600;'>
                            Modifica articolo →
                        </a>
                    </div>
                ";
            }

            Mail::send([], [], function ($m) use ($authorEmail, $subject, $body) {
                $m->to($authorEmail)->subject($subject)->html($body);
            });
        } catch (\Exception $e) {
            // Silenzioso
        }
    }
}