<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('admin.profile', ['user' => auth()->user()]);
    }

    public function update(Request $request)
    {
        $user      = auth()->user();
        $validated = $request->validate([
            'name'     => 'required|max:100',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'bio'      => 'nullable|max:500',
            'twitter'  => 'nullable|max:50',
            'linkedin' => 'nullable|url|max:200',
        ]);
        $user->update($validated);
        return back()->with('success', 'Profilo aggiornato.');
    }

    public function updatePhoto(Request $request)
    {
        $request->validate(['photo' => 'required|image|mimes:jpeg,png,webp|max:2048']);
        $user     = auth()->user();
        $file     = $request->file('photo');
        $diskName = 'author-' . $user->id . '-' . now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('assets/img'), $diskName);
        $user->update(['photo' => $diskName]);
        return back()->with('success', 'Foto aggiornata.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ]);
        $user = auth()->user();
        if (!Hash::check($request->input('current_password'), $user->password)) {
            return back()->withErrors(['current_password' => 'Password attuale non corretta.']);
        }
        $user->update(['password' => Hash::make($request->input('password'))]);
        return back()->with('success', 'Password aggiornata.');
    }
}
