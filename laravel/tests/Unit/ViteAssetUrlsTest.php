<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * AppServiceProvider registers Vite::createAssetPathsUsing() to make
 * Vite-generated asset URLs root-relative instead of the default absolute
 * URL built from the current request's host — see that provider's
 * docblock. Needed because comble.show can be viewed through Discord's
 * Activity proxy, where the page is displayed from a different origin than
 * our server sees in the request; an absolute URL with our own host baked
 * in gets blocked as a direct external fetch by Discord's sandboxed iframe
 * (confirmed in production — every asset request failed with "Failed to
 * fetch" until this fix).
 */
class ViteAssetUrlsTest extends TestCase
{
    public function test_vite_asset_urls_are_root_relative_not_absolute(): void
    {
        $url = Vite::asset('resources/js/comble.js');

        $this->assertStringStartsWith('/', $url);
        $this->assertStringNotContainsString('://', $url);
    }

    /** Reproduces the actual bug: requesting the page against a host other than the app's own doesn't change the generated asset URL's host. */
    public function test_vite_asset_urls_do_not_pick_up_an_arbitrary_requests_host(): void
    {
        $this->get('http://comble.example.test/', ['Host' => 'comble.example.test']);

        $url = Vite::asset('resources/js/comble.js');

        $this->assertStringStartsWith('/', $url);
        $this->assertStringNotContainsString('comble.example.test', $url);
        $this->assertStringNotContainsString('://', $url);
    }
}
