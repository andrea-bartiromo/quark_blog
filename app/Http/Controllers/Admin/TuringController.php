<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpecialPage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TuringController extends Controller
{
    private const SLUG = 'turing';

    public function edit()
    {
        $page = $this->firstOrCreateTuringPage();

        return view('admin.turing-lite', compact('page'));
    }

    public function update(Request $request)
    {
        $page = $this->firstOrCreateTuringPage();
        $data = $this->validatedData($request);
        $data = $this->resolveTopLevelImages($request, $data, $page->content ?? []);

        $page->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'content' => $this->contentPayload($request, $data),
        ]);

        return redirect()
            ->route('admin.turing')
            ->with('success', 'Speciale Turing aggiornato.');
    }

    private function firstOrCreateTuringPage(): SpecialPage
    {
        return SpecialPage::firstOrCreate(
            ['slug' => self::SLUG],
            [
                'title' => 'Alan Turing',
                'description' => 'Speciale editoriale dedicato ad Alan Turing, Enigma e intelligenza artificiale.',
                'is_active' => true,
                'content' => [],
            ]
        );
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'title' => 'required|max:150',
            'description' => 'nullable|max:500',
            'is_active' => 'nullable|boolean',
            'hero_kicker' => 'nullable|max:120',
            'hero_title' => 'required|max:150',
            'hero_lead' => 'required|max:900',
            'hero_primary_label' => 'nullable|max:80',
            'hero_secondary_label' => 'nullable|max:80',
            'hero_portrait_title' => 'nullable|max:150',
            'hero_portrait_text' => 'nullable|max:220',
            'hero_portrait_initials' => 'nullable|max:12',
            'hero_portrait_years' => 'nullable|max:40',
            'hero_terminal_title' => 'nullable|max:120',
            'hero_terminal_lines' => 'nullable|array',
            'hero_terminal_lines.*' => 'nullable|max:160',
            'hero_background_image' => 'nullable|max:500',
            'hero_background_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'hero_background_image_remove' => 'nullable|boolean',
            'hero_portrait_image' => 'nullable|max:500',
            'hero_portrait_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'hero_portrait_image_remove' => 'nullable|boolean',
            'home_teaser_kicker' => 'nullable|max:120',
            'home_teaser_title' => 'nullable|max:180',
            'home_teaser_text' => 'nullable|max:700',
            'home_teaser_cta_label' => 'nullable|max:100',
            'home_teaser_terminal_title' => 'nullable|max:120',
            'home_teaser_terminal_lines' => 'nullable|array',
            'home_teaser_terminal_lines.*' => 'nullable|max:160',
            'home_teaser_background_image' => 'nullable|max:500',
            'home_teaser_background_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'home_teaser_background_image_remove' => 'nullable|boolean',
            'intro_kicker' => 'nullable|max:120',
            'intro_title' => 'required|max:180',
            'intro_text' => 'required|max:900',
            'intro_background_image' => 'nullable|max:500',
            'intro_background_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'intro_background_image_remove' => 'nullable|boolean',
            'why_kicker' => 'nullable|max:120',
            'why_title' => 'required|max:180',
            'why_text' => 'required|max:1000',
            'why_background_image' => 'nullable|max:500',
            'why_background_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'why_background_image_remove' => 'nullable|boolean',
            'final_kicker' => 'nullable|max:120',
            'final_title' => 'required|max:180',
            'final_text' => 'required|max:500',
            'final_background_image' => 'nullable|max:500',
            'final_background_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'final_background_image_remove' => 'nullable|boolean',
            'cards' => 'nullable|array',
            'cards.*.label' => 'nullable|max:120',
            'cards.*.title' => 'nullable|max:150',
            'cards.*.text' => 'nullable|max:500',
            'cards.*.url' => 'nullable|max:255',
            'cards.*.style' => 'nullable|in:enigma,ai,legacy',
            'cards.*.image' => 'nullable|max:500',
            'cards.*.image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'editorial_blocks' => 'nullable|array',
            'editorial_blocks.*.key' => 'nullable|max:80',
            'editorial_blocks.*.enabled' => 'nullable|boolean',
            'editorial_blocks.*.layout' => 'nullable|in:text,image_left,image_right,dark_card,feature_grid',
            'editorial_blocks.*.kicker' => 'nullable|max:120',
            'editorial_blocks.*.title' => 'nullable|max:180',
            'editorial_blocks.*.text' => 'nullable|max:1400',
            'editorial_blocks.*.image' => 'nullable|max:500',
            'editorial_blocks.*.image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'editorial_blocks.*.image_remove' => 'nullable|boolean',
            'editorial_blocks.*.background_image' => 'nullable|max:500',
            'editorial_blocks.*.background_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'editorial_blocks.*.background_image_remove' => 'nullable|boolean',
            'editorial_blocks.*.link_label' => 'nullable|max:100',
            'editorial_blocks.*.link_url' => 'nullable|max:255',
            'internal_links' => 'nullable|array',
            'decorative_images' => 'nullable|array',
            'why_items' => 'nullable|array',
            'timeline' => 'nullable|array',
        ]);
    }

    private function resolveTopLevelImages(Request $request, array $data, array $existingContent): array
    {
        foreach ($this->topLevelImageFields() as $field => $contentPath) {
            if ($request->boolean($field.'_remove')) {
                $data[$field] = null;

                continue;
            }

            if ($uploaded = $this->uploadedImage($request, $field.'_upload')) {
                $data[$field] = $uploaded;

                continue;
            }

            $manual = $request->has($field) ? trim((string) ($data[$field] ?? '')) : null;
            $previous = data_get($existingContent, $contentPath);

            $data[$field] = $manual !== '' ? $manual : $previous;
        }

        return $data;
    }

    private function topLevelImageFields(): array
    {
        return [
            'hero_background_image' => 'hero.background_image',
            'hero_portrait_image' => 'hero.portrait_image',
            'home_teaser_background_image' => 'home_teaser.background_image',
            'intro_background_image' => 'intro.background_image',
            'why_background_image' => 'why.background_image',
            'final_background_image' => 'final.background_image',
        ];
    }

    private function contentPayload(Request $request, array $data): array
    {
        return [
            'hero' => $this->heroPayload($request, $data),
            'home_teaser' => $this->homeTeaserPayload($request, $data),
            'intro' => $this->introPayload($data),
            'cards' => $this->cards($request),
            'editorial_blocks' => $this->editorialBlocks($request),
            'internal_links' => $request->input('internal_links', []),
            'decorative_images' => $request->input('decorative_images', []),
            'why' => $this->whyPayload($request, $data),
            'timeline' => $request->input('timeline', []),
            'final' => $this->finalPayload($data),
        ];
    }

    private function heroPayload(Request $request, array $data): array
    {
        return [
            'kicker' => $data['hero_kicker'] ?? null,
            'title' => $data['hero_title'],
            'lead' => $data['hero_lead'],
            'primary_label' => $data['hero_primary_label'] ?? 'Esplora Enigma',
            'secondary_label' => $data['hero_secondary_label'] ?? 'Vai all’IA moderna',
            'portrait_title' => $data['hero_portrait_title'] ?? null,
            'portrait_text' => $data['hero_portrait_text'] ?? null,
            'portrait_initials' => $data['hero_portrait_initials'] ?? 'AT',
            'portrait_years' => $data['hero_portrait_years'] ?? '1912 / 1954',
            'terminal_title' => $data['hero_terminal_title'] ?? 'Turing Archive',
            'terminal_lines' => $this->filledLines($request, 'hero_terminal_lines'),
            'background_image' => $data['hero_background_image'] ?? null,
            'portrait_image' => $data['hero_portrait_image'] ?? null,
        ];
    }

    private function homeTeaserPayload(Request $request, array $data): array
    {
        return [
            'kicker' => $data['home_teaser_kicker'] ?? 'Special Project',
            'title' => $data['home_teaser_title'] ?? 'Alan Turing: l’uomo che ha decifrato il futuro.',
            'text' => $data['home_teaser_text'] ?? 'Una nuova area speciale di Quark dedicata a Enigma, alla nascita del computer, al Test di Turing e al legame con l’intelligenza artificiale moderna.',
            'cta_label' => $data['home_teaser_cta_label'] ?? 'Entra nella Turing Experience',
            'terminal_title' => $data['home_teaser_terminal_title'] ?? 'TURING ARCHIVE',
            'terminal_lines' => $this->filledLines($request, 'home_teaser_terminal_lines'),
            'background_image' => $data['home_teaser_background_image'] ?? null,
        ];
    }

    private function introPayload(array $data): array
    {
        return [
            'kicker' => $data['intro_kicker'] ?? null,
            'title' => $data['intro_title'],
            'text' => $data['intro_text'],
            'background_image' => $data['intro_background_image'] ?? null,
        ];
    }

    private function whyPayload(Request $request, array $data): array
    {
        return [
            'kicker' => $data['why_kicker'] ?? null,
            'title' => $data['why_title'],
            'text' => $data['why_text'],
            'background_image' => $data['why_background_image'] ?? null,
            'items' => $request->input('why_items', []),
        ];
    }

    private function finalPayload(array $data): array
    {
        return [
            'kicker' => $data['final_kicker'] ?? null,
            'title' => $data['final_title'],
            'text' => $data['final_text'],
            'background_image' => $data['final_background_image'] ?? null,
        ];
    }

    private function filledLines(Request $request, string $key): array
    {
        return collect($request->input($key, []))
            ->filter(fn ($line) => filled($line))
            ->values()
            ->all();
    }

    private function cards(Request $request): array
    {
        return collect($request->input('cards', []))
            ->map(function ($item, $index) use ($request) {
                $item['image'] = $this->resolveNestedImage($request, $item, "cards.$index", 'image');

                return $item;
            })
            ->filter(fn ($item) => filled($item['title'] ?? null) || filled($item['image'] ?? null))
            ->values()
            ->all();
    }

    private function editorialBlocks(Request $request): array
    {
        return collect($request->input('editorial_blocks', []))
            ->map(function ($item, $index) use ($request) {
                $item['enabled'] = ! empty($item['enabled']);
                $item['image'] = $this->resolveNestedImage($request, $item, "editorial_blocks.$index", 'image');
                $item['background_image'] = $this->resolveNestedImage($request, $item, "editorial_blocks.$index", 'background_image');

                return $item;
            })
            ->filter(fn ($item) => filled($item['title'] ?? null)
                || filled($item['text'] ?? null)
                || filled($item['image'] ?? null)
                || filled($item['background_image'] ?? null))
            ->values()
            ->all();
    }

    private function resolveNestedImage(Request $request, array $item, string $baseKey, string $field): ?string
    {
        if ($request->boolean($baseKey.'.'.$field.'_remove')) {
            return null;
        }

        $uploaded = $this->uploadedImage($request, $baseKey.'.'.$field.'_upload');
        $manual = trim((string) ($item[$field] ?? ''));

        return $uploaded ?: ($manual !== '' ? $manual : null);
    }

    private function uploadedImage(Request $request, string $key): ?string
    {
        if (! $request->hasFile($key) || ! $request->file($key)->isValid()) {
            return null;
        }

        $file = $request->file($key);
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $diskName = $filename.'-'.date('YmdHis').'-'.substr(md5((string) random_int(1, PHP_INT_MAX)), 0, 6).'.'.$extension;
        $uploadPath = public_path('assets/img');

        if (! is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $file->move($uploadPath, $diskName);

        return $diskName;
    }
}
