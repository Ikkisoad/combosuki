<?php

namespace Tests\Feature\Admin;

use App\Models\ExternalSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_external_sites_list(): void
    {
        ExternalSite::create(['title' => 'SuperCombo.gg', 'url' => 'https://supercombo.gg/', 'order' => 0]);

        $this->actingAs(User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]));

        $this->get(route('admin.external-sites.index'))->assertOk()->assertSee('SuperCombo.gg');
    }

    public function test_non_admin_cannot_view_the_external_sites_list(): void
    {
        $this->actingAs(User::create(['nickname' => 'regular', 'password' => 'password123']));

        $this->get(route('admin.external-sites.index'))->assertRedirect()->assertSessionHas('error');
    }

    public function test_admin_can_add_a_site(): void
    {
        $this->actingAs(User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]));

        $this->post(route('admin.external-sites.store'), [
            'action' => 'Add',
            'title' => 'Dustloop wiki',
            'url' => 'https://www.dustloop.com/',
            'order' => 1,
        ])->assertRedirect(route('admin.external-sites.index'));

        $this->assertDatabaseHas('external_site', ['title' => 'Dustloop wiki', 'url' => 'https://www.dustloop.com/']);
    }

    public function test_admin_can_update_a_site(): void
    {
        $site = ExternalSite::create(['title' => 'Old Title', 'url' => 'https://old.example/', 'order' => 0]);

        $this->actingAs(User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]));

        $this->post(route('admin.external-sites.store'), [
            'action' => 'Update',
            'id' => $site->id,
            'title' => 'New Title',
            'url' => 'https://new.example/',
            'order' => 2,
        ])->assertRedirect(route('admin.external-sites.index'));

        $this->assertDatabaseHas('external_site', ['id' => $site->id, 'title' => 'New Title', 'url' => 'https://new.example/', 'order' => 2]);
    }

    public function test_admin_can_delete_a_site(): void
    {
        $site = ExternalSite::create(['title' => 'To Delete', 'url' => 'https://delete.example/', 'order' => 0]);

        $this->actingAs(User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]));

        $this->post(route('admin.external-sites.store'), [
            'action' => 'Delete',
            'id' => $site->id,
        ])->assertRedirect(route('admin.external-sites.index'));

        $this->assertDatabaseMissing('external_site', ['id' => $site->id]);
    }

    public function test_about_page_lists_sites_in_order(): void
    {
        ExternalSite::create(['title' => 'Second', 'url' => 'https://second.example/', 'order' => 1]);
        ExternalSite::create(['title' => 'First', 'url' => 'https://first.example/', 'order' => 0]);

        $response = $this->get(route('about'));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Second'), strpos($content, 'First'));
    }
}
