<?php

namespace App\Http\Controllers\Redazione;

use App\Http\Controllers\Controller;
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
        private readonly ImageService $imageService,
        private readonly MediaService $mediaService,
        private readonly PublicMediaSyncService $publicMediaSync,
        private readonly MediaRetirementService $mediaRetirement
    ) {}

    public function edit()
    {
        return view('redazione.profile', [
            'user' => auth()->user(),
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'bio' => 'nullable|string|max:500',
            'twitter' => 'nullable|string|max:100',
            'linkedin' => 'nullable|url|max:255',
        ]);

        $user->update($data);

        return redirect()
            ->route('redazione.profile')
            ->with('success', 'Profilo aggiornato.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (! Hash::check(
            $request->current_password,
            $user->password
        )) {
            return back()->withErrors([
                'current_password' => 'La password attuale non è corretta.',
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        if (method_exists(auth(), 'logoutOtherDevices')) {
            auth()->logoutOtherDevices($request->password);
        }

        return redirect()
            ->route('redazione.profile')
            ->with('success', 'Password aggiornata.');
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

        if ($previousPhoto && $previousPhoto !== $diskName) {
            $this->mediaRetirement->retireIfUnused($previousPhoto, 'redazione_profile_photo_replaced');
        }

        return redirect()
            ->route('redazione.profile')
            ->with('success', 'Foto profilo aggiornata.');
    }
}