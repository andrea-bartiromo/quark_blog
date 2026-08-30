<?php

namespace App\Http\Requests\Admin;

use App\Models\ArticleSource;
use App\Services\EditorialSources\SourceReferenceNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * EDITORIAL TRUST (Missioni 26-27) — validazione dell'editor delle fonti.
 *
 * L'autorizzazione è già quella dell'intero gruppo admin.*
 * (middleware ['auth','editor']): chi può modificare il corpo di un
 * articolo può già modificarne le fonti. Nessun privilegio nuovo, stesso
 * ragionamento documentato per Admin\ArticleRevisionController.
 */
class UpdateArticleSourcesRequest extends FormRequest
{
    /**
     * Tetto volutamente generoso ma finito: nessun articolo divulgativo
     * cita 50 fonti, e senza limite un payload costruito a mano potrebbe
     * far crescere la pagina pubblica senza controllo.
     */
    public const MAX_SOURCES = 50;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sources' => ['nullable', 'array', 'max:'.self::MAX_SOURCES],
            'sources.*.id' => ['nullable', 'integer'],
            'sources.*.title' => ['required', 'string', 'max:255'],
            'sources.*.author_or_org' => ['nullable', 'string', 'max:255'],
            'sources.*.url' => ['nullable', 'string', 'max:2048'],
            'sources.*.doi' => ['nullable', 'string', 'max:255'],
            'sources.*.source_type' => ['nullable', 'string', 'in:'.implode(',', ArticleSource::types())],
            'sources.*.published_on' => ['nullable', 'date_format:Y-m-d'],
            'sources.*.accessed_on' => ['nullable', 'date_format:Y-m-d'],
            'sources.*.editorial_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sources.*.title.required' => 'Il titolo della fonte è obbligatorio.',
            'sources.max' => 'Non è possibile collegare più di '.self::MAX_SOURCES.' fonti a un articolo.',
            'sources.*.published_on.date_format' => 'La data di pubblicazione deve essere nel formato AAAA-MM-GG.',
            'sources.*.accessed_on.date_format' => 'La data di consultazione deve essere nel formato AAAA-MM-GG.',
        ];
    }

    /**
     * Righe interamente vuote vengono scartate PRIMA della validazione:
     * l'editor mostra sempre una riga bianca pronta all'uso, e lasciarla
     * intatta non deve produrre un errore "titolo obbligatorio" su una
     * fonte che l'utente non ha mai iniziato a compilare.
     */
    protected function prepareForValidation(): void
    {
        $rows = $this->input('sources');

        if (! is_array($rows)) {
            return;
        }

        $kept = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $meaningful = ['title', 'author_or_org', 'url', 'doi', 'published_on', 'accessed_on', 'editorial_note'];

            $isBlank = true;

            foreach ($meaningful as $field) {
                if (trim((string) ($row[$field] ?? '')) !== '') {
                    $isBlank = false;
                    break;
                }
            }

            if ($isBlank) {
                continue;
            }

            $kept[] = $row;
        }

        // array_values(): gli indici devono restare contigui, altrimenti i
        // messaggi di errore puntano a una riga che nel form non esiste.
        $this->merge(['sources' => $kept]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rows = (array) $this->input('sources', []);

            $normalizer = app(SourceReferenceNormalizer::class);

            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $this->validateReferences($validator, $normalizer, $index, $row);
                $this->validateDates($validator, $index, $row);
            }
        });
    }

    private function validateReferences(
        Validator $validator,
        SourceReferenceNormalizer $normalizer,
        int|string $index,
        array $row,
    ): void {
        $rawUrl = trim((string) ($row['url'] ?? ''));
        $rawDoi = trim((string) ($row['doi'] ?? ''));

        $url = $rawUrl === '' ? null : $normalizer->normalizeUrl($rawUrl);
        $doi = $rawDoi === '' ? null : $normalizer->normalizeDoi($rawDoi);

        if ($rawUrl !== '' && $url === null) {
            $validator->errors()->add(
                "sources.$index.url",
                'Indirizzo non valido: sono ammessi solo URL assoluti che iniziano con https:// .'
            );
        }

        if ($rawDoi !== '' && $doi === null) {
            $validator->errors()->add(
                "sources.$index.doi",
                'DOI non riconosciuto. Formato atteso: 10.1234/identificativo (accettati anche doi:… e https://doi.org/…).'
            );
        }

        // Una fonte senza alcun riferimento verificabile non è una fonte:
        // sarebbe una citazione che il lettore non può controllare, cioè
        // esattamente ciò che questa funzionalità esiste per evitare.
        if ($rawUrl === '' && $rawDoi === '') {
            $validator->errors()->add(
                "sources.$index.url",
                'Indica almeno un riferimento verificabile: un URL https:// oppure un DOI.'
            );
        }
    }

    private function validateDates(Validator $validator, int|string $index, array $row): void
    {
        $today = now()->toDateString();

        foreach (['published_on' => 'di pubblicazione', 'accessed_on' => 'di consultazione'] as $field => $label) {
            $value = trim((string) ($row[$field] ?? ''));

            if ($value === '' || $validator->errors()->has("sources.$index.$field")) {
                continue;
            }

            // Una data futura è sempre un errore di battitura, mai un dato
            // editoriale reale: non si consulta né si pubblica domani.
            if ($value > $today) {
                $validator->errors()->add(
                    "sources.$index.$field",
                    'La data '.$label.' non può essere nel futuro.'
                );
            }
        }
    }
}
