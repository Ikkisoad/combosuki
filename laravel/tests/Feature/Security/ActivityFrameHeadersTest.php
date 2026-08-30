<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SecurityHeaders is global middleware wrapping the whole router (see its
 * docblock for why the Discord-permissive branch has to live inside that
 * one file rather than a route-scoped middleware). This asserts the carve-
 * out is scoped correctly: activity.*-named routes, and (only when
 * DISCORD_ACTIVITY_DOMAIN is configured) requests to that exact Host —
 * every other route/host must keep the strict same-origin default that
 * blocks framing entirely.
 */
class ActivityFrameHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_activity_named_route_allows_discord_to_frame_it(): void
    {
        $response = $this->post(route('activity.comble.token'));

        $response->assertHeader('Content-Security-Policy', 'frame-ancestors https://discord.com https://*.discord.com https://*.discordsays.com');
        $this->assertFalse($response->headers->has('X-Frame-Options'));
    }

    public function test_an_ordinary_route_still_blocks_framing(): void
    {
        $response = $this->get(route('home'));

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
    }

    /**
     * comble.show isn't activity.*-named, so it needs the second carve-out:
     * any request whose Host matches the configured comble.* subdomain.
     * SecurityHeaders reads config() fresh on every request (unlike routing
     * decisions, which are made once at boot — see routes/comble.php's
     * docblock on why that distinction matters here), so this can set it
     * mid-test without the env/boot-timing problems a routing test would
     * hit.
     */
    public function test_a_request_to_the_configured_activity_domain_allows_framing(): void
    {
        config(['services.discord.activity_domain' => 'comble.example.test']);

        $response = $this->get('http://comble.example.test/whatever-resolves-here');

        $response->assertHeader('Content-Security-Policy', 'frame-ancestors https://discord.com https://*.discord.com https://*.discordsays.com');
        $this->assertFalse($response->headers->has('X-Frame-Options'));
    }

    /** With no DISCORD_ACTIVITY_DOMAIN configured (the default), no Host can match it — comble.show on the main domain keeps the strict default. */
    public function test_comble_show_on_the_main_domain_blocks_framing_when_no_activity_domain_is_configured(): void
    {
        $response = $this->get(route('comble.show'));

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
    }

    /** A Host that merely resembles the configured domain must not match. */
    public function test_an_unrelated_host_does_not_get_the_activity_domains_relaxed_csp(): void
    {
        config(['services.discord.activity_domain' => 'comble.example.test']);

        $response = $this->get('http://not-comble.example.test/whatever-resolves-here');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
    }
}
