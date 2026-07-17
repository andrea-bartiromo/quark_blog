<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Article;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CommentController extends Controller
{
    public function store(Request $request)
    {
        // Honeypot anti-spam
        if ($request->input('website') !== '') {
            return response()->json(['ok' => true]);
        }

        $validated = $request->validate([
            'article_id' => 'required|integer',
            'name'       => 'required|max:80',
            'email'      => 'required|email',
            'body'       => 'required|min:10|max:1500',
        ]);

        $article = Article::published()
            ->whereKey($validated['article_id'])
            ->first();

        if (! $article) {
            throw ValidationException::withMessages([
                'article_id' => 'L\'articolo selezionato non è disponibile per i commenti.',
            ]);
        }

        $comment = Comment::create($validated + ['status' => 'pending']);

        // Notifica email all'editor per nuovo commento
        try {
            $adminEmail = User::where('role', 'editor')->value('email');
            if ($adminEmail) {
                $articleTitle = $article->title;
                $moderaUrl = route('admin.comments');

                Mail::send([], [], function ($m) use ($comment, $adminEmail, $articleTitle, $moderaUrl) {
                    $m->to($adminEmail)
                      ->subject('💬 Nuovo commento da moderare — Quark')
                      ->html("
                        <div style='font-family:Arial,sans-serif;max-width:500px;padding:1.5rem;'>
                          <h2 style='color:#0d9488;margin-bottom:.75rem;'>Nuovo commento da moderare</h2>
                          <table style='width:100%;border-collapse:collapse;font-size:.875rem;margin-bottom:1rem;'>
                            <tr><td style='padding:.4rem 0;color:#6b7280;width:80px;'>Autore</td><td style='font-weight:600;'>" . htmlspecialchars($comment->name) . "</td></tr>
                            <tr><td style='padding:.4rem 0;color:#6b7280;'>Articolo</td><td>" . htmlspecialchars($articleTitle) . "</td></tr>
                          </table>
                          <div style='background:#f9fafb;border-radius:8px;padding:1rem;margin-bottom:1rem;font-size:.875rem;color:#374151;line-height:1.6;'>
                            " . htmlspecialchars($comment->body) . "
                          </div>
                          <a href='{$moderaUrl}' style='display:inline-block;background:#0d9488;color:#fff;padding:.6rem 1.25rem;border-radius:6px;text-decoration:none;font-weight:600;'>
                            Modera commento →
                          </a>
                        </div>
                      ");
                });
            }
        } catch (\Exception $e) {
            // Silenzioso — il commento è salvato anche se l'email fallisce
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Commento inviato. Sarà pubblicato dopo la moderazione.',
        ]);
    }
}