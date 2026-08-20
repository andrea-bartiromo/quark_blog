<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CollaboratorController extends Controller
{
    public function index()
    {
        $collaborators = User::where('role', 'author')
            ->withCount([
                'articles',
                'articles as published_count' => fn ($query) =>
                    $query->where('status', 'published'),
                'articles as review_count' => fn ($query) =>
                    $query->where('status', 'review'),
            ])
            ->orderBy('name')
            ->get();

        return view('admin.collaborators', compact('collaborators'));
    }

    public function create()
    {
        return view('admin.collaborator-form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|max:100',
            'email' => 'required|email|unique:users,email',
            'bio' => 'nullable|max:500',
            'twitter' => 'nullable|max:100',
            'linkedin' => 'nullable|url|max:255',
        ]);

        $password = Str::random(12);

        $user = new User();

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = Hash::make($password);
        $user->role = 'author';
        $user->bio = $data['bio'] ?? null;
        $user->twitter = $data['twitter'] ?? null;
        $user->linkedin = $data['linkedin'] ?? null;

        $user->save();

        try {
            $loginUrl = route('redazione.login');

            Mail::send([], [], function ($message) use ($user, $password, $loginUrl) {
                $message
                    ->to($user->email)
                    ->subject('Benvenuto nella redazione di Kairus!')
                    ->html("
                        <div style='font-family:Arial,sans-serif;max-width:540px;padding:1.5rem;'>
                            <h1 style='color:#0d9488;margin-bottom:.5rem;'>
                                Kairus.
                            </h1>

                            <p style='color:#6b7280;font-size:.82rem;margin-bottom:1.5rem;'>
                                La scienza spiegata come si deve
                            </p>

                            <h2 style='color:#111827;margin-bottom:1rem;'>
                                Benvenuto nella redazione!
                            </h2>

                            <p style='color:#374151;line-height:1.7;margin-bottom:1rem;'>
                                Ciao {$user->name}, sei stato aggiunto come collaboratore di Kairus.
                                Puoi iniziare a scrivere articoli accedendo alla tua area personale.
                            </p>

                            <div style='background:#f0fdfa;border-radius:8px;padding:1rem 1.25rem;margin-bottom:1.5rem;'>
                                <p style='margin:0 0 .5rem;font-weight:700;color:#0f766e;'>
                                    Le tue credenziali di accesso:
                                </p>

                                <p style='margin:0;color:#374151;font-size:.875rem;'>
                                    Email:
                                    <strong>{$user->email}</strong>
                                </p>

                                <p style='margin:0;color:#374151;font-size:.875rem;'>
                                    Password temporanea:
                                    <strong style='font-family:monospace;background:#e5e7eb;padding:.1rem .4rem;border-radius:4px;'>
                                        {$password}
                                    </strong>
                                </p>
                            </div>

                            <p style='color:#6b7280;font-size:.82rem;margin-bottom:1.25rem;'>
                                Ti consigliamo di cambiare la password dopo il primo accesso dal tuo profilo.
                            </p>

                            <a
                                href='{$loginUrl}'
                                style='display:inline-block;background:#0d9488;color:#fff;padding:.65rem 1.5rem;border-radius:8px;text-decoration:none;font-weight:700;'
                            >
                                Accedi alla redazione
                            </a>

                            <p style='color:#9ca3af;font-size:.72rem;margin-top:2rem;'>
                                I tuoi articoli saranno revisionati dall'editor prima della pubblicazione.
                            </p>
                        </div>
                    ");
            });
        } catch (\Throwable $exception) {
            report($exception);
        }

        ActivityLog::record(
            'Collaboratore aggiunto',
            'user',
            $user->id,
            $user->name
        );

        return redirect()
            ->route('admin.collaborators')
            ->with(
                'success',
                "Collaboratore {$user->name} aggiunto. Email di benvenuto inviata a {$user->email}."
            );
    }

    public function edit(User $user)
    {
        $this->ensureAuthor($user);

        return view('admin.collaborator-form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $this->ensureAuthor($user);

        $data = $request->validate([
            'name' => 'required|max:100',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'bio' => 'nullable|max:500',
            'twitter' => 'nullable|max:100',
            'linkedin' => 'nullable|url|max:255',
        ]);

        $user->update($data);

        ActivityLog::record(
            'Collaboratore modificato',
            'user',
            $user->id,
            $user->name
        );

        return redirect()
            ->route('admin.collaborators')
            ->with(
                'success',
                "Profilo di {$user->name} aggiornato."
            );
    }

    public function resetPassword(User $user)
    {
        $this->ensureAuthor($user);

        $password = Str::random(12);

        $user->update([
            'password' => Hash::make($password),
        ]);

        try {
            $loginUrl = route('redazione.login');

            Mail::send([], [], function ($message) use ($user, $password, $loginUrl) {
                $message
                    ->to($user->email)
                    ->subject('Password reimpostata � Kairus')
                    ->html("
                        <div style='font-family:Arial,sans-serif;max-width:480px;padding:1.5rem;'>
                            <h2 style='color:#0d9488;'>
                                Password reimpostata
                            </h2>

                            <p style='color:#374151;'>
                                Ciao {$user->name}, la tua password � stata reimpostata dall'editor.
                            </p>

                            <div style='background:#f0fdfa;border-radius:8px;padding:1rem;margin:1rem 0;'>
                                <p style='margin:0;'>
                                    Nuova password temporanea:
                                    <strong style='font-family:monospace;background:#e5e7eb;padding:.1rem .4rem;border-radius:4px;'>
                                        {$password}
                                    </strong>
                                </p>
                            </div>

                            <p style='color:#6b7280;font-size:.82rem;'>
                                Cambia la password dal tuo profilo dopo l'accesso.
                            </p>

                            <a
                                href='{$loginUrl}'
                                style='display:inline-block;background:#0d9488;color:#fff;padding:.6rem 1.25rem;border-radius:6px;text-decoration:none;font-weight:600;'
                            >
                                Accedi
                            </a>
                        </div>
                    ");
            });
        } catch (\Throwable $exception) {
            report($exception);
        }

        return redirect()
            ->route('admin.collaborators')
            ->with(
                'success',
                "Password di {$user->name} reimpostata. Email inviata."
            );
    }

    public function destroy(User $user)
    {
        $this->ensureAuthor($user);

        if ($user->id === auth()->id()) {
            abort(403);
        }

        $name = $user->name;

        $editor = User::whereIn('role', ['editor', 'admin'])
            ->orderByRaw("CASE WHEN role = 'editor' THEN 0 ELSE 1 END")
            ->first();

        if ($editor) {
            Article::where('user_id', $user->id)
                ->update([
                    'user_id' => $editor->id,
                ]);

            // S9 — media.user_id ha un vincolo FK con onDelete('cascade')
            // (a differenza di articles.user_id, gia' riassegnato sopra):
            // senza questa riassegnazione, eliminare il collaboratore
            // cancellerebbe silenziosamente dal DB ogni riga Media che
            // aveva caricato — non un file orfano, una vera perdita della
            // voce di catalogo per file che restano sul disco e possono
            // essere ancora attivamente in uso come copertina di un
            // articolo pubblicato.
            Media::where('user_id', $user->id)
                ->update([
                    'user_id' => $editor->id,
                ]);
        }

        $user->delete();

        ActivityLog::record(
            'Collaboratore rimosso',
            'user',
            null,
            $name
        );

        return redirect()
            ->route('admin.collaborators')
            ->with(
                'success',
                "Collaboratore {$name} rimosso. I suoi articoli sono stati riassegnati."
            );
    }

    private function ensureAuthor(User $user): void
    {
        if ($user->role !== 'author') {
            abort(403, 'Operazione consentita solo sui collaboratori.');
        }
    }
}