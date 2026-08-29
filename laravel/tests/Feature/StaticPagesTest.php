<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaticPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_page_loads(): void
    {
        $this->get('/terms')->assertStatus(200);
    }

    public function test_privacy_page_loads(): void
    {
        $this->get('/privacy')->assertStatus(200);
    }

    public function test_home_page_links_to_terms_and_privacy(): void
    {
        $this->get('/')
            ->assertStatus(200)
            ->assertSee(route('terms'), false)
            ->assertSee(route('privacy'), false);
    }
}
