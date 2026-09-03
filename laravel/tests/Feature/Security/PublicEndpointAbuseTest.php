<?php

namespace Tests\Feature\Security;

use App\Models\BotHit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * GET /t (HoneypotController::hit) is the app's only unauthenticated,
 * row-creating endpoint, and it writes three attacker-influenced values:
 * ?from, the client IP, and the User-Agent header. HoneypotTest covers the
 * happy path; these cover what a bot that isn't merely following the link
 * sends — oversized values, hostile strings, and inputs of the wrong PHP
 * type.
 *
 * The legacy .php redirects at the bottom of routes/web.php take raw
 * request() values straight into redirect()->route(), so they're checked
 * here for open-redirect and header-injection too. Those pass because
 * route() prefixes the app URL and rawurlencodes the parameter — that isn't
 * obvious from reading the closures, which is exactly why it's pinned.
 */
class PublicEndpointAbuseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The "array" cache store (see phpunit.xml) lives for the whole test
        // process and backs the rate limiter, so a throttle assertion would
        // otherwise pass or fail depending on test ordering.
        Cache::flush();
    }

    public function test_an_oversized_from_parameter_is_truncated_rather_than_erroring(): void
    {
        $this->get(route('honeypot.hit', ['from' => str_repeat('a', 10000)]))->assertNoContent();

        $this->assertSame(255, mb_strlen(BotHit::sole()->path));
    }

    public function test_an_oversized_user_agent_is_truncated_rather_than_erroring(): void
    {
        $this->withHeaders(['User-Agent' => str_repeat('b', 10000)])
            ->get(route('honeypot.hit'))
            ->assertNoContent();

        $this->assertSame(512, mb_strlen(BotHit::sole()->user_agent));
    }

    public function test_a_hostile_from_parameter_is_stored_as_data(): void
    {
        $payload = '<script>alert(1)</script>\' OR 1=1--';

        $this->get(route('honeypot.hit', ['from' => $payload]))->assertNoContent();

        $this->assertSame($payload, BotHit::sole()->path);
        $this->assertDatabaseCount('bot_hits', 1);
    }

    /**
     * ?from[]=a makes query('from') an array. Casting an array to string
     * emits E_WARNING, which Laravel's error handler rethrows as
     * ErrorException — a 500 with a full stack trace on a public, and
     * (before this was fixed) unthrottled, endpoint. A single-URL loop was
     * enough to flood laravel.log.
     */
    public function test_an_array_from_parameter_does_not_error(): void
    {
        $this->get('/t?from[]=a')->assertNoContent();

        $this->assertSame('', BotHit::sole()->path);
    }

    public function test_the_honeypot_is_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->get(route('honeypot.hit'))->assertNoContent();
        }

        $this->get(route('honeypot.hit'))->assertStatus(429);

        $this->assertSame(60, BotHit::count());
    }

    /**
     * The legacy closures pass an unvalidated query parameter as a route
     * parameter. route() builds an absolute URL against the app's own base
     * and rawurlencodes the segment, so a scheme, a protocol-relative host,
     * or a backslash trick can't move the Location off this host.
     */
    public function test_a_legacy_redirect_cannot_be_pointed_at_an_external_host(): void
    {
        $base = rtrim(config('app.url'), '/');

        $hostile = ['https://evil.test', '//evil.test', '/\\evil.test', 'evil.test/x'];

        foreach ($hostile as $payload) {
            foreach ([
                '/game/index.php?gameid='.urlencode($payload),
                '/list/list.php?listid='.urlencode($payload),
                '/list/show.php?id='.urlencode($payload),
            ] as $url) {
                $location = $this->get($url)->headers->get('Location');

                $this->assertStringStartsWith($base, (string) $location, "{$url} escaped the app's base URL");
            }
        }
    }

    public function test_a_legacy_search_redirect_cannot_inject_a_response_header(): void
    {
        $response = $this->get('/list/search.php?q=a%0d%0aSet-Cookie:%20injected=1');

        $location = (string) $response->headers->get('Location');

        $this->assertStringNotContainsString("\r", $location);
        $this->assertStringNotContainsString("\n", $location);

        // Laravel always sets XSRF-TOKEN/session cookies, so the assertion
        // is that no *additional* cookie was smuggled in via the CRLF.
        foreach ($response->headers->all('Set-Cookie') as $cookie) {
            $this->assertStringNotContainsString('injected', $cookie);
        }
    }
}
