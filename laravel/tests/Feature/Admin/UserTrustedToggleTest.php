<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTrustedToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_toggle_a_users_trusted_status(): void
    {
        $admin = User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
        $user = User::create(['nickname' => 'regular', 'password' => 'password123']);

        $this->actingAs($admin);

        $this->post(route('admin.users.trusted.update', $user))->assertRedirect(route('admin.users.index'));
        $this->assertTrue($user->fresh()->trusted_user);

        $this->post(route('admin.users.trusted.update', $user))->assertRedirect(route('admin.users.index'));
        $this->assertFalse($user->fresh()->trusted_user);
    }

    public function test_non_admin_cannot_toggle_a_users_trusted_status(): void
    {
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $user = User::create(['nickname' => 'regular', 'password' => 'password123']);

        $this->actingAs($trusted);

        $this->post(route('admin.users.trusted.update', $user))->assertRedirect()->assertSessionHas('error');
        $this->assertFalse((bool) $user->fresh()->trusted_user);
    }
}
