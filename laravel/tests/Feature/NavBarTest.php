<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comble lives on its own comble.* subdomain (routes/comble.php) — the nav
 * bar opens it in a new tab rather than navigating there in place, since
 * that page has no way back to the main site itself (see
 * resources/views/comble/show.blade.php).
 */
class NavBarTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_comble_link_opens_in_a_new_tab(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<a class="nav-link" href="'.preg_quote(route('comble.show'), '#').'" target="_blank" rel="noopener">Comble</a>#',
            $html,
        );
    }
}
