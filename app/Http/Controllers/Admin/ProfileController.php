<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsExclusionService;
use App\Services\ImageService;
use App\Services\MediaRetirementService;
use App\Services\MediaService;
use App\Services\PublicMediaSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class ProfileController extends Controller
{
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly ImageService $imageService,
        private readonly PublicMediaSyncService $publicMediaSync,
        private readonly MediaRetirementService $mediaRetirement,
        private readonly AnalyticsExclusionService $analyticsExclusion
    ) {}

    public function edit(Request $request)
    {
        return view('admin.profile', [
            'user' => auth()->user(),
            // Il cookie decide quale azione (escludi/riattiva) proporre —
            // sempre disponibile indipendentemente dal resto, cosi'
            // un'esclusione impostata ora resta pronta per quando
            // l'ambiente/Measurement ID tornassero validi.
            'analyticsExcluded' => $this->analyticsExclusion->isExcluded($request),
            'analyticsExcludedSince' => $this->analyticsExclusion->excludedSince($request),
            // Stato REALMENTE effettivo per questa richiesta (cookie +
            // ambiente + Measurement ID insieme): senza questo, la pagina
            // direbbe "ATTIVO" anche quando GA4 e' gia' disattivato per
            // tutt'altro motivo (ambiente non-production, kill-switch
            // ANALYTICS_ENABLED=false, Measurement ID vuoto) — un'
            // informazione di stato scorretta esattamente nel posto
            // pensato per verificarlo.
            'analyticsEffectivelyActive' => $this->analyticsExclusion->shouldLoadAnalytics($request),
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'bio' => 'nullable|string|max:500',
            'twitter' => 'nullable|string|max:50',
            'linkedin' => 'nullable|url|max:200',
        ]);

        $user->update($validated);

        return back()->with(
            'success',
            'Profilo aggiornato.'
        );
    }

    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        $user = auth()->user();
        $file = $request->file('photo');

        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();

        /*
         * L'estensione viene rilevata dal MIME del file
         * e non dal nome originale inviato dal browser.
         */
        $extension = $this->imageService->safeExtension($file);

        $diskName = $this->imageService->buildFileName(
            $file,
            $extension,
            'author-'.$user->id.'-'.now()->format('YmdHis')
        );

        $fullPath = $this->imageService->upload(
            $file,
            public_path('assets/img'),
            $diskName
        );

        /*
         * Ridimensiona e ricodifica la foto profilo.
         */
        $this->imageService->resizeAndCompress(
            $fullPath,
            $extension,
            800,
            [
                'jpg' => 85,
                'png' => 7,
                'webp' => 85,
            ],
            preserveTransparency: true,
            alwaysReencode: true,
            logErrors: true
        );

        try {
            $this->publicMediaSync->create($fullPath, $diskName);
        } catch (RuntimeException $exception) {
            $this->publicMediaSync->cleanupAfterFailedCreate($fullPath);
            report($exception);

            return back()->withErrors(['photo' => 'Impossibile pubblicare la nuova foto. Riprova o contatta l\'assistenza.']);
        }

        $this->mediaService->register(
            $request->user(),
            $originalName,
            $diskName,
            $mimeType,
            filesize($fullPath) ?: 0
        );

        $previousPhoto = $user->photo;

        $user->update([
            'photo' => $diskName,
        ]);

        /*
         * Ritira la foto precedente solo dopo che il profilo punta già
         * alla nuova: a questo punto il controllo "è ancora referenziata?"
         * non troverà più questo stesso utente puntare al file vecchio.
         * Best-effort: un fallimento qui non deve mai togliere all'utente
         * una foto valida, che a questo punto è già quella nuova.
         */
        if ($previousPhoto && $previousPhoto !== $diskName) {
            $this->mediaRetirement->retireIfUnused($previousPhoto, 'admin_profile_photo_replaced');
        }

        return back()->with(
            'success',
            'Foto aggiornata.'
        );
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (! Hash::check(
            $request->input('current_password'),
            $user->password
        )) {
            return back()->withErrors([
                'current_password' => 'Password attuale non corretta.',
            ]);
        }

        $user->update([
            'password' => Hash::make(
                $request->input('password')
            ),
        ]);

        /*
         * Invalida le altre sessioni dell'utente dopo
         * il cambio della password, se supportato dal driver.
         */
        if (method_exists(auth(), 'logoutOtherDevices')) {
            auth()->logoutOtherDevices(
                $request->input('password')
            );
        }

        return back()->with(
            'success',
            'Password aggiornata.'
        );
    }
}