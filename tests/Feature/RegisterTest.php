<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-02: registrasi selalu menghasilkan Pelanggan dan wajib persetujuan Kebijakan Privasi.
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'nama' => 'Rina Pratiwi',
            'email' => 'rina@contoh.test',
            'no_hp' => '081234567890',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'setuju_privasi' => '1',
        ], $override);
    }

    public function test_registration_ignores_role_and_creates_pelanggan(): void
    {
        $this->post('/register', $this->payload(['role' => 'SUPER_ADMIN', 'aktif' => '0']))
            ->assertRedirect('/menu');

        $user = User::where('email', 'rina@contoh.test')->firstOrFail();
        $this->assertSame(Role::Pelanggan, $user->role);
        $this->assertTrue($user->aktif);
        $this->assertSame('1.0', $user->privasi_versi);
        $this->assertNotNull($user->privasi_disetujui_at);
        $this->assertTrue(password_verify('rahasia123', $user->password_hash));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_requires_privacy_consent(): void
    {
        $payload = $this->payload();
        unset($payload['setuju_privasi']);

        $this->post('/register', $payload)->assertSessionHasErrors('setuju_privasi');

        $this->assertDatabaseMissing('users', ['email' => 'rina@contoh.test']);
        $this->assertGuest();
    }

    public function test_registration_validates_email_password_and_phone(): void
    {
        User::factory()->create(['email' => 'rina@contoh.test']);

        $this->post('/register', $this->payload(['password' => 'pendek', 'password_confirmation' => 'pendek', 'no_hp' => 'abc']))
            ->assertSessionHasErrors(['email', 'password', 'no_hp']);

        $this->post('/register', $this->payload([
            'email' => 'baru@contoh.test',
            'password' => str_repeat('a', 65),
            'password_confirmation' => str_repeat('a', 65),
        ]))->assertSessionHasErrors('password');
    }
}
