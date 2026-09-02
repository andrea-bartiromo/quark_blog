<?php

namespace Tests\Feature\Measurement;

use App\Services\Telemetry\EditorialEventContract;
use App\Services\Telemetry\SourceChannelResolver;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Measurement Closeout (Missione 5) — attribuzione della sorgente,
 * allowlisted e priva di conservazione di URL/query completi.
 */
class SourceChannelResolverTest extends TestCase
{
    private function resolver(): SourceChannelResolver
    {
        return new SourceChannelResolver;
    }

    private function request(array $query = [], ?string $referer = null, string $host = 'kairus.test'): Request
    {
        $request = Request::create('https://'.$host.'/articolo/test', 'GET', $query);

        if ($referer !== null) {
            $request->headers->set('referer', $referer);
        }

        return $request;
    }

    public function test_no_referer_and_no_utm_resolves_to_direct(): void
    {
        $this->assertSame('direct', $this->resolver()->resolve($this->request()));
    }

    public function test_google_referer_resolves_to_google(): void
    {
        $request = $this->request(referer: 'https://www.google.com/search?q=kairus');

        $this->assertSame('google', $this->resolver()->resolve($request));
    }

    public function test_google_italy_domain_also_resolves_to_google(): void
    {
        $request = $this->request(referer: 'https://www.google.it/search?q=kairus');

        $this->assertSame('google', $this->resolver()->resolve($request));
    }

    public function test_facebook_referer_resolves_to_facebook(): void
    {
        $request = $this->request(referer: 'https://m.facebook.com/');

        $this->assertSame('facebook', $this->resolver()->resolve($request));
    }

    public function test_linkedin_referer_resolves_to_linkedin(): void
    {
        $request = $this->request(referer: 'https://www.linkedin.com/feed/');

        $this->assertSame('linkedin', $this->resolver()->resolve($request));
    }

    public function test_own_host_referer_resolves_to_internal(): void
    {
        $request = $this->request(referer: 'https://kairus.test/notizie', host: 'kairus.test');

        $this->assertSame('internal', $this->resolver()->resolve($request));
    }

    public function test_own_host_percorsi_referer_resolves_to_percorso(): void
    {
        $request = $this->request(referer: 'https://kairus.test/percorsi/fisica-fondamentale', host: 'kairus.test');

        $this->assertSame('percorso', $this->resolver()->resolve($request));
    }

    public function test_unrecognized_external_referer_resolves_to_unknown(): void
    {
        $request = $this->request(referer: 'https://some-random-blog.example/post/1');

        $this->assertSame('unknown', $this->resolver()->resolve($request));
    }

    public function test_utm_source_newsletter_wins_over_a_direct_referer(): void
    {
        $request = $this->request(query: ['utm_source' => 'newsletter']);

        $this->assertSame('newsletter', $this->resolver()->resolve($request));
    }

    public function test_utm_source_wins_over_a_conflicting_referer(): void
    {
        // La utm che noi stessi costruiamo è un segnale più affidabile del
        // referrer del browser (che potrebbe essere il client email, non la
        // fonte reale del link) — quando entrambe sono presenti, l'utm vince.
        $request = $this->request(
            query: ['utm_source' => 'newsletter'],
            referer: 'https://mail.google.com/',
        );

        $this->assertSame('newsletter', $this->resolver()->resolve($request));
    }

    public function test_utm_source_google_discover_resolves_to_discover(): void
    {
        $request = $this->request(query: ['utm_source' => 'google-discover']);

        $this->assertSame('discover', $this->resolver()->resolve($request));
    }

    public function test_an_unrecognized_utm_source_falls_back_to_referer_logic(): void
    {
        $request = $this->request(query: ['utm_source' => 'some-unknown-campaign-id']);

        $this->assertSame('direct', $this->resolver()->resolve($request));
    }

    public function test_result_is_always_within_the_allowlisted_taxonomy(): void
    {
        $cases = [
            $this->request(),
            $this->request(referer: 'https://www.google.com/'),
            $this->request(referer: 'https://facebook.com/'),
            $this->request(referer: 'https://linkedin.com/'),
            $this->request(query: ['utm_source' => 'newsletter']),
            $this->request(referer: 'https://random.example/'),
        ];

        $allowed = EditorialEventContract::SOURCE_CHANNELS;

        foreach ($cases as $request) {
            $this->assertContains($this->resolver()->resolve($request), $allowed);
        }
    }

    public function test_the_resolver_never_returns_the_raw_referer_url(): void
    {
        $refererWithToken = 'https://random.example/?secret_token=abc123&user=jdoe';
        $request = $this->request(referer: $refererWithToken);

        $result = $this->resolver()->resolve($request);

        $this->assertStringNotContainsString('secret_token', $result);
        $this->assertStringNotContainsString('jdoe', $result);
        $this->assertLessThanOrEqual(16, strlen($result));
    }
}
