<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\Request;

class AdController extends Controller
{
    public function index()
    {
        $ads = Ad::orderBy('position')->orderByDesc('priority')->get();

        return view('admin.ads', compact('ads'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|max:100',
            'position' => 'required|in:'.implode(',', array_keys(Ad::POSITIONS)),
            'type' => 'required|in:adsense,banner,html',
            'active' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0|max:100',
            'adsense_publisher_id' => 'nullable|max:50',
            'adsense_slot_id' => 'nullable|max:20',
            'adsense_format' => 'nullable|in:auto,horizontal,rectangle,vertical',
            'banner_image' => 'nullable|max:255',
            'banner_url' => 'nullable|url|max:500',
            'banner_alt' => 'nullable|max:150',
            'html_code' => 'nullable',
            'notes' => 'nullable|max:500',
        ]);

        $data['active'] = $request->boolean('active');
        Ad::create($data);

        return redirect()->route('admin.ads')->with('success', 'Annuncio creato con successo.');
    }

    public function update(Request $request, Ad $ad)
    {
        $data = $request->validate([
            'name' => 'required|max:100',
            'position' => 'required|in:'.implode(',', array_keys(Ad::POSITIONS)),
            'type' => 'required|in:adsense,banner,html',
            'active' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0|max:100',
            'adsense_publisher_id' => 'nullable|max:50',
            'adsense_slot_id' => 'nullable|max:20',
            'adsense_format' => 'nullable|in:auto,horizontal,rectangle,vertical',
            'banner_image' => 'nullable|max:255',
            'banner_url' => 'nullable|url|max:500',
            'banner_alt' => 'nullable|max:150',
            'html_code' => 'nullable',
            'notes' => 'nullable|max:500',
        ]);

        $data['active'] = $request->boolean('active');
        $ad->update($data);

        return redirect()->route('admin.ads')->with('success', 'Annuncio aggiornato.');
    }

    public function toggle(Ad $ad)
    {
        $ad->update(['active' => ! $ad->active]);

        return redirect()->route('admin.ads')->with('success',
            $ad->active ? 'Annuncio attivato.' : 'Annuncio disattivato.'
        );
    }

    public function destroy(Ad $ad)
    {
        $ad->delete();

        return redirect()->route('admin.ads')->with('success', 'Annuncio eliminato.');
    }
}
