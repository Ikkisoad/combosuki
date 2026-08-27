<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LastLoginTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_login_records_last_login_at(): void
    {
        $user = User::create(['nickname' => 'normal', 'password' => 'password123']);
        $this->assertNull($user->last_login_at);

        Carbon::setTestNow('2026-08-27 12:00:00');

        $this->post(route('login.attempt'), ['nickname' => 'normal', 'password' => 'password123'])
            ->assertRedirect(route('home'));

        $this->assertTrue($user->fresh()->last_login_at->equalTo(Carbon::now()));
    }

    public function test_admin_dashboard_shows_last_login_for_each_user(): void
    {
        $admin = User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
        $neverLoggedIn = User::create(['nickname' => 'fresh', 'password' => 'password123']);

        Carbon::setTestNow('2026-08-27 12:00:00');
        $neverLoggedIn->forceFill(['last_login_at' => now()])->save();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Last Login')
            ->assertSee('2026-08-27 12:00')
            ->assertSee('Never');
    }
}
