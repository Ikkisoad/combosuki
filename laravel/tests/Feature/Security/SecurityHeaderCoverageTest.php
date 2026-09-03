<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * ActivityFrameHeadersTest already covers the frame-ancestors carve-out in
 * detail. What it doesn't cover is whether the other four headers survive
 * every shape of response the app produces — SecurityHeaders is appended
 * globally in bootstrap/app.php, so it wraps the router and therefore also
 * wraps exception-rendered responses, redirects and JSON.
 */
class SecurityHeaderCoverageTest extends TestCase
{
    use RefreshDatabase;

    private const BASELINE = [
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
    ];

    private function assertBaselineHeaders(TestResponse $response, string $context): void
    {
        foreach (self::BASELINE as $header => $value) {
            $this->assertSame($value, $response->headers->get($header), "{$context} is missing {$header}");
        }
    }

    public function test_the_baseline_headers_are_present_on_a_normal_page(): void
    {
        $response = $this->get(route('home'))->assertOk();

        $this->assertBaselineHeaders($response, 'A normal page');
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
    }

    /**
     * A 404 is rendered by the exception handler, not a controller, so this
     * is the case most likely to slip past middleware that was added at the
     * wrong layer.
     */
    public function test_the_baseline_headers_survive_a_404_and_a_redirect(): void
    {
        $this->assertBaselineHeaders(
            $this->get('/this-route-does-not-exist')->assertNotFound(),
            'A 404 response'
        );

        $this->assertBaselineHeaders(
            $this->get(route('admin.dashboard'))->assertRedirect(route('login')),
            'A redirect response'
        );
    }

    public function test_the_baseline_headers_are_present_on_a_json_endpoint(): void
    {
        $user = User::create(['nickname' => 'member', 'password' => 'password123']);

        $response = $this->actingAs($user)
            ->getJson(route('users.search', ['q' => 'me']))
            ->assertOk();

        $this->assertBaselineHeaders($response, 'A JSON response');
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->assertNull(
            $this->get('http://localhost/')->headers->get('Strict-Transport-Security'),
            'HSTS was sent over plain HTTP, where a downgrade attacker could strip it anyway.'
        );

        $this->assertSame(
            'max-age=31536000; includeSubDomains',
            $this->get('https://localhost/')->headers->get('Strict-Transport-Security')
        );
    }

    /**
     * Documents current behaviour rather than guarding a fix.
     *
     * SecurityHeaders relaxes frame-ancestors for any request whose Host
     * equals services.discord.activity_domain — on *every* route, not just
     * the activity ones — and bootstrap/app.php configures no trustHosts(),
     * so that is the raw Host header. This is not exploitable as deployed: a
     * browser sets Host from the URL authority, so a page can never choose
     * its own, and an attacker forging the header by hand only changes the
     * response they themselves receive.
     *
     * It would become exploitable behind a shared cache that keys on path
     * without Vary: Host, since a poisoned entry would then serve the relaxed
     * CSP to everyone. If a CDN is ever put in front of this app, add
     * $middleware->trustHosts(...) and flip this assertion.
     */
    public function test_a_request_host_matching_the_activity_domain_relaxes_framing_on_any_route(): void
    {
        config(['services.discord.activity_domain' => 'comble.example.test']);

        // The Host is the whole condition — this is an ordinary page, not an
        // activity.* route, and no other property of the request is special.
        $response = $this->get('http://comble.example.test/')->assertOk();

        $this->assertStringContainsString(
            'frame-ancestors https://discord.com',
            (string) $response->headers->get('Content-Security-Policy')
        );
        $this->assertNull($response->headers->get('X-Frame-Options'));
    }

    /**
     * The counterpart to the test above: X-Forwarded-Host must not be able to
     * do the same thing. Symfony discards forwarded headers unless the proxy
     * is trusted, and no trustProxies() is configured.
     */
    public function test_a_forwarded_host_header_cannot_relax_framing(): void
    {
        config(['services.discord.activity_domain' => 'comble.example.test']);

        $response = $this->get(route('home'), ['X-Forwarded-Host' => 'comble.example.test'])->assertOk();

        $this->assertSame("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }
}
