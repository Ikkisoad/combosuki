<?php

namespace Tests\Unit;

use App\Support\MainSiteUrl;
use Tests\TestCase;

class MainSiteUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://combosuki.com']);
    }

    public function test_route_points_at_the_configured_app_url(): void
    {
        $this->assertSame('https://combosuki.com/games', MainSiteUrl::route('games.index'));
    }

    /**
     * The actual bug this class fixes: plain route() defaults to the
     * *current* request's host for a non-domain-scoped route, which is
     * wrong for a link whose whole point is getting someone off whichever
     * domain is currently serving the page — see the class docblock.
     */
    public function test_route_ignores_the_current_requests_host(): void
    {
        $this->get('http://comble.example.test/', ['Host' => 'comble.example.test']);

        $this->assertSame('https://combosuki.com/games', MainSiteUrl::route('games.index'));
    }

    public function test_a_trailing_slash_on_app_url_does_not_produce_a_double_slash(): void
    {
        config(['app.url' => 'https://combosuki.com/']);

        $this->assertSame('https://combosuki.com/games', MainSiteUrl::route('games.index'));
    }
}
