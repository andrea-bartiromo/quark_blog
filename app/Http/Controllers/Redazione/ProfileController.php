<?php

namespace App\Http\Controllers\Redazione;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('redazione.profile', ['user' => auth()->user()]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => 'required|max:100',
            'bio' => 'nullable|max:500',
            'twitter' => 'nullable|max:100',
            'linkedin' => 'nullable|url|max:255',
        ]);

        $user->update($data);

        return redirect()->route('redazione.profile')
            ->with('success', 'Profilo aggiornato.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'La password attuale non è corretta.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return redirect()->route('redazione.profile')
            ->with('success', 'Password aggiornata.');
    }

    public function updatePhoto(Request $request)
    {
        $request->validate(['photo' => 'required|image|mimes:jpeg,jpg,png|max:2048']);

        $user = auth()->user();
        $path = $request->file('photo')->store('photos', 'public');

        if ($user->photo) {
            \Storage::disk('public')->delete($user->photo);
        }

        $user->update(['photo' => $path]);

        return redirect()->route('redazione.profile')
            ->with('success', 'Foto profilo aggiornata.');
    }
}
