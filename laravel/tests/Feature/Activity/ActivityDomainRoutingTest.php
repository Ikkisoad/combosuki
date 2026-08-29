<?php

namespace Tests\Feature\Activity;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * routes/activity.php registers under a dedicated subdomain (Route::domain())
 * when DISCORD_ACTIVITY_DOMAIN is configured, or falls back to an
 * "/activity/comble" prefix on the main domain otherwise — see that file's
 * docblock for why (Discord's Root URL Mapping always loads the mapped
 * target's own "/", so the Activity has to actually live at a dedicated
 * subdomain's root in production). Every other Activity test in this suite
 * runs without DISCORD_ACTIVITY_DOMAIN set (not part of phpunit.xml's env
 * block), exercising the prefix fallback — this file is the only one that
 * exercises the domain-scoped branch.
 *
 * That branch decision happens once, at application boot, from the env var
 * — a plain config() call in setUp() would be too late, since routing has
 * already been registered by then. putenv()/$_ENV are set before
 * parent::setUp() triggers the (per-test-method) fresh application boot,
 * and unset again in tearDown() so the change can't leak into any other
 * test in the same PHPUnit process.
 */
class ActivityDomainRoutingTest extends TestCase
{
    use RefreshDatabase;

    private const DOMAIN = 'comble.example.test';

    protected function setUp(): void
    {
        putenv('DISCORD_ACTIVITY_DOMAIN='.self::DOMAIN);
        $_ENV['DISCORD_ACTIVITY_DOMAIN'] = self::DOMAIN;
        $_SERVER['DISCORD_ACTIVITY_DOMAIN'] = self::DOMAIN;

        parent::setUp();

        SiteSetting::current()->update(['discord_activity_enabled' => true]);
        SiteSetting::forgetCurrent();
    }

    protected function tearDown(): void
    {
        putenv('DISCORD_ACTIVITY_DOMAIN');
        unset($_ENV['DISCORD_ACTIVITY_DOMAIN'], $_SERVER['DISCORD_ACTIVITY_DOMAIN']);

        parent::tearDown();
    }

    public function test_the_activity_serves_from_the_configured_subdomains_root(): void
    {
        $this->get('http://'.self::DOMAIN.'/')->assertOk();
    }

    public function test_the_route_names_stay_the_same_as_the_prefix_fallback(): void
    {
        $this->assertStringContainsString(self::DOMAIN, route('activity.comble.show'));
        $this->assertStringContainsString(self::DOMAIN, route('activity.comble.token'));
        $this->assertStringNotContainsString('/activity/comble', route('activity.comble.show'));
    }

    /** The main domain's own "/" must stay the normal homepage, not the Activity, even with the subdomain configured. */
    public function test_the_main_domains_root_is_unaffected(): void
    {
        $this->get('/')->assertOk()->assertDontSee('activity-comble-root', false);
    }

    /** A request to the old "/activity/comble" prefix on the main domain must not also resolve once a dedicated subdomain is configured. */
    public function test_the_prefix_path_on_the_main_domain_is_no_longer_registered(): void
    {
        $this->get('/activity/comble')->assertNotFound();
    }
}
