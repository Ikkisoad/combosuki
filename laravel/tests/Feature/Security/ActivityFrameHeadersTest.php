<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SecurityHeaders is global middleware wrapping the whole router (see its
 * docblock for why the Discord-permissive branch has to live inside that
 * one file rather than a route-scoped middleware). This asserts the carve-
 * out is scoped to activity.* routes only — every other route must keep the
 * strict same-origin default that blocks framing entirely.
 */
class ActivityFrameHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_activity_route_allows_discord_to_frame_it(): void
    {
        $response = $this->get(route('activity.comble.show'));

        $response->assertHeader('Content-Security-Policy', 'frame-ancestors https://discord.com https://*.discord.com https://*.discordsays.com');
        $this->assertFalse($response->headers->has('X-Frame-Options'));
    }

    public function test_an_ordinary_route_still_blocks_framing(): void
    {
        $response = $this->get(route('home'));

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
    }
}
