<?php

namespace Tests\Feature\Measurement;

use App\Services\Telemetry\EditorialEventContract;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Measurement Closeout (Missione 2) — copertura di schema, privacy e
 * compatibilità del contratto canonico degli eventi editoriali.
 */
class EditorialEventContractTest extends TestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'event_name' => EditorialEventContract::ARTICLE_VIEWED,
            'schema_version' => EditorialEventContract::SCHEMA_VERSION,
            'session_key' => str_repeat('a', 64),
            'article_id' => 1,
            'source_channel' => 'direct',
            'occurred_at' => now(),
        ], $overrides);
    }

    public function test_a_valid_article_viewed_payload_passes(): void
    {
        $validated = EditorialEventContract::validate($this->validPayload());

        $this->assertSame(EditorialEventContract::ARTICLE_VIEWED, $validated['event_name']);
        $this->assertSame(1, $validated['schema_version']);
    }

    public function test_an_unknown_field_is_rejected_fail_closed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EditorialEventContract::validate($this->validPayload(['email' => 'reader@example.com']));
    }

    public function test_the_rejection_message_never_echoes_the_offending_value(): void
    {
        try {
            EditorialEventContract::validate($this->validPayload(['token' => 'super-secret-token-123']));
            $this->fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringNotContainsString('super-secret-token-123', $exception->getMessage());
            $this->assertStringContainsString('token', $exception->getMessage());
        }
    }

    public function test_ip_address_field_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EditorialEventContract::validate($this->validPayload(['ip' => '203.0.113.5']));
    }

    public function test_raw_url_field_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EditorialEventContract::validate($this->validPayload(['referer_url' => 'https://example.com/x?token=abc']));
    }

    public function test_an_unknown_event_name_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EditorialEventContract::validate($this->validPayload(['event_name' => 'article.deleted']));
    }

    public function test_an_unknown_source_channel_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EditorialEventContract::validate($this->validPayload(['source_channel' => 'tiktok']));
    }

    public function test_an_unknown_transition_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EditorialEventContract::validate($this->validPayload([
            'event_name' => EditorialEventContract::TRANSITION_AVAILABLE,
            'target_article_id' => 2,
            'transition_type' => 'sideways',
        ]));
    }

    public function test_a_missing_required_field_is_rejected(): void
    {
        $payload = $this->validPayload();
        unset($payload['session_key']);

        $this->expectException(InvalidArgumentException::class);

        EditorialEventContract::validate($payload);
    }

    public function test_a_session_key_that_is_not_a_64_char_hex_digest_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EditorialEventContract::validate($this->validPayload(['session_key' => 'not-a-digest']));
    }

    public function test_a_plausible_raw_session_id_is_rejected_because_it_is_not_a_hex_digest(): void
    {
        // Un id di sessione Laravel grezzo (40 char alfanumerico) non deve
        // MAI passare come session_key: la barriera è puramente strutturale
        // (forma esadecimale a 64 char), non un controllo di provenienza —
        // ma è comunque l'ultima difesa contro un errore di chiamata che
        // passasse l'id di sessione al posto del suo pseudonimo.
        $this->expectException(InvalidArgumentException::class);

        EditorialEventContract::validate($this->validPayload(['session_key' => str_repeat('x', 40)]));
    }

    public function test_an_unsupported_schema_version_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EditorialEventContract::validate($this->validPayload(['schema_version' => 99]));
    }

    public function test_transition_available_requires_a_transition_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EditorialEventContract::validate($this->validPayload([
            'event_name' => EditorialEventContract::TRANSITION_AVAILABLE,
            'target_article_id' => 2,
            'article_id' => 1,
        ]));
    }

    public function test_transition_available_requires_a_target_article(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EditorialEventContract::validate($this->validPayload([
            'event_name' => EditorialEventContract::TRANSITION_AVAILABLE,
            'transition_type' => EditorialEventContract::TRANSITION_NEXT,
            'article_id' => 1,
        ]));
    }

    public function test_article_viewed_requires_an_article_id(): void
    {
        $payload = $this->validPayload();
        unset($payload['article_id']);

        $this->expectException(InvalidArgumentException::class);

        EditorialEventContract::validate($payload);
    }

    public function test_path_viewed_requires_a_content_cluster_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EditorialEventContract::validate($this->validPayload([
            'event_name' => EditorialEventContract::PATH_VIEWED,
            'article_id' => null,
        ]));
    }

    public function test_newsletter_subscribed_does_not_require_an_article(): void
    {
        $validated = EditorialEventContract::validate($this->validPayload([
            'event_name' => EditorialEventContract::NEWSLETTER_SUBSCRIBED,
            'article_id' => null,
        ]));

        $this->assertSame(EditorialEventContract::NEWSLETTER_SUBSCRIBED, $validated['event_name']);
        $this->assertNull($validated['article_id']);
    }

    public function test_all_five_event_names_are_stable_and_dot_namespaced(): void
    {
        $this->assertSame([
            'article.viewed',
            'path.viewed',
            'article.transition_available',
            'path.link_available',
            'newsletter.subscribed',
        ], EditorialEventContract::EVENT_NAMES);
    }

    public function test_all_nine_source_channels_are_the_allowlisted_taxonomy(): void
    {
        $this->assertSame([
            'google', 'discover', 'facebook', 'linkedin', 'newsletter',
            'internal', 'percorso', 'direct', 'unknown',
        ], EditorialEventContract::SOURCE_CHANNELS);
    }

    public function test_validate_normalizes_ids_to_integers_and_drops_nothing_unexpected(): void
    {
        $validated = EditorialEventContract::validate($this->validPayload(['article_id' => '42']));

        $this->assertSame(42, $validated['article_id']);
        $this->assertSame(array_keys($validated), [
            'event_name', 'schema_version', 'session_key', 'article_id',
            'target_article_id', 'content_cluster_id', 'transition_type',
            'source_channel', 'context_position', 'occurred_at',
        ]);
    }
}
