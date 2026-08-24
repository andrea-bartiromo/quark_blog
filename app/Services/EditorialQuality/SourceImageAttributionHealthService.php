<?php

namespace App\Services\EditorialQuality;

use App\Models\Article;
use DOMDocument;
use DOMElement;

/**
 * Diagnostica read-only per attribuzione media e fonti editoriali.
 * Non blocca publish e non modifica alcun articolo.
 */
class SourceImageAttributionHealthService
{
    public const OK = 'OK';

    public const WARNING = 'WARNING';

    public const NOT_APPLICABLE = 'NOT_APPLICABLE';

    /**
     * @return array<int, array{id:string,status:string,reason:string}>
     */
    public function evaluate(Article $article): array
    {
        return [
            $this->coverAlt($article),
            $this->coverCreditSource($article),
            $this->coverSourceUrl($article),
            $this->coverLicense($article),
            $this->externalBodyImages($article),
            $this->editorialSources($article),
        ];
    }

    private function coverAlt(Article $article): array
    {
        if (blank($article->cover_image)) {
            return $this->result('cover_alt', self::NOT_APPLICABLE, 'Nessuna cover presente.');
        }

        return blank($article->cover_alt)
            ? $this->result('cover_alt', self::WARNING, 'Cover presente senza testo alternativo.')
            : $this->result('cover_alt', self::OK, 'Alt cover presente.');
    }

    private function coverCreditSource(Article $article): array
    {
        if (blank($article->cover_image)) {
            return $this->result('cover_credit_source', self::NOT_APPLICABLE, 'Nessuna cover presente.');
        }

        $credit = trim((string) $article->cover_credit);
        $source = trim((string) $article->cover_source);

        if ($credit !== '' && $source === '') {
            return $this->result('cover_credit_source', self::WARNING, 'Credito presente ma fonte media assente.');
        }

        if ($source !== '' && $credit === '') {
            return $this->result('cover_credit_source', self::WARNING, 'Fonte media presente ma credito assente.');
        }

        if ($source === '' && $credit === '') {
            return $this->result('cover_credit_source', self::WARNING, 'Cover senza credito e fonte media.');
        }

        return $this->result('cover_credit_source', self::OK, 'Credito e fonte media presenti.');
    }

    private function coverSourceUrl(Article $article): array
    {
        if (blank($article->cover_image)) {
            return $this->result('cover_source_url', self::NOT_APPLICABLE, 'Nessuna cover presente.');
        }

        $url = trim((string) $article->cover_source_url);

        if ($url === '') {
            return $this->result('cover_source_url', self::NOT_APPLICABLE, 'URL fonte non compilato: il dominio corrente non impone che ogni fonte media abbia un URL.');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return $this->result('cover_source_url', self::WARNING, 'URL fonte media non valido o non HTTP(S).');
        }

        return $this->result('cover_source_url', self::OK, 'URL fonte media valido.');
    }

    private function coverLicense(Article $article): array
    {
        if (blank($article->cover_image)) {
            return $this->result('cover_license', self::NOT_APPLICABLE, 'Nessuna cover presente.');
        }

        return blank($article->cover_license)
            ? $this->result('cover_license', self::WARNING, 'Cover senza informazione di licenza.')
            : $this->result('cover_license', self::OK, 'Licenza cover presente.');
    }

    private function externalBodyImages(Article $article): array
    {
        $external = $this->externalImageSources((string) $article->body);

        if ($external === []) {
            return $this->result('external_body_images', self::NOT_APPLICABLE, 'Nessuna immagine esterna nel body.');
        }

        return $this->result(
            'external_body_images',
            self::WARNING,
            count($external).' immagine/i esterna/e nel body senza un modello per-image di attribution verificabile.'
        );
    }

    private function editorialSources(Article $article): array
    {
        $sources = trim((string) $article->primary_sources);

        return $sources === ''
            ? $this->result('editorial_sources', self::WARNING, 'Nessuna fonte scientifica/editoriale nel campo dedicato. Questo segnale resta distinto dalla fonte della cover.')
            : $this->result('editorial_sources', self::OK, 'Fonti scientifiche/editoriali presenti nel campo dedicato.');
    }

    /** @return array<int, string> */
    private function externalImageSources(string $html): array
    {
        if (trim($html) === '' || strip_tags($html) === $html) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $sources = [];

        foreach ($dom->getElementsByTagName('img') as $image) {
            /** @var DOMElement $image */
            $src = trim($image->getAttribute('src'));
            if (preg_match('#^https?://#i', $src) === 1) {
                $sources[] = $src;
            }
        }

        return array_values(array_unique($sources));
    }

    /** @return array{id:string,status:string,reason:string} */
    private function result(string $id, string $status, string $reason): array
    {
        return compact('id', 'status', 'reason');
    }
}
