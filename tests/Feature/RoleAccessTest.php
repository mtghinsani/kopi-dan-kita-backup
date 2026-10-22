<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-03: pembatasan halaman per role.
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        foreach (['/menu', '/karyawan', '/admin', '/profil'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_pelanggan_cannot_open_staff_or_admin_pages(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/menu')->assertOk();
        $this->get('/karyawan')->assertForbidden();
        $this->get('/admin')->assertForbidden();
    }

    public function test_karyawan_cannot_open_admin_or_customer_pages(): void
    {
        $this->actingAs(User::factory()->karyawan()->create());

        $this->get('/karyawan')->assertOk();
        $this->get('/admin')->assertForbidden();
        $this->get('/menu')->assertForbidden();
    }

    public function test_super_admin_can_open_admin_and_staff_pages(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get('/admin')->assertOk();
        $this->get('/karyawan')->assertOk();
    }

    public function test_privacy_policy_is_public(): void
    {
        $this->get('/kebijakan-privasi')->assertOk()->assertSee('Kebijakan Privasi');
    }
}
