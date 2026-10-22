<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * T-01: login memakai kolom password_hash, redirect sesuai role, akun nonaktif ditolak, throttle 429.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public static function roleRedirects(): array
    {
        return [
            'super admin' => ['superAdmin', '/admin'],
            'karyawan' => ['karyawan', '/karyawan'],
            'pelanggan' => [null, '/menu'],
        ];
    }

    #[DataProvider('roleRedirects')]
    public function test_user_logs_in_with_password_hash_and_is_redirected_by_role(?string $state, string $home): void
    {
        $factory = User::factory();
        $user = ($state ? $factory->{$state}() : $factory)->create(['email' => 'user@kopikita.test']);

        $response = $this->post('/login', ['email' => 'user@kopikita.test', 'password' => 'password']);

        $response->assertRedirect($home);
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected_with_generic_message(): void
    {
        User::factory()->create(['email' => 'user@kopikita.test']);

        $this->from('/login')
            ->post('/login', ['email' => 'user@kopikita.test', 'password' => 'salah-sekali'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        User::factory()->nonaktif()->create(['email' => 'nonaktif@kopikita.test']);

        $this->post('/login', ['email' => 'nonaktif@kopikita.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_sixth_attempt_within_a_minute_is_throttled(): void
    {
        User::factory()->create(['email' => 'user@kopikita.test']);

        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', ['email' => 'user@kopikita.test', 'password' => 'salah'])->assertStatus(302);
        }

        $this->post('/login', ['email' => 'user@kopikita.test', 'password' => 'password'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $user->forceFill(['aktif' => false])->save();

        $this->get('/menu')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_logged_in_user_visiting_login_is_sent_home(): void
    {
        $this->actingAs(User::factory()->karyawan()->create());

        $this->get('/login')->assertRedirect('/karyawan');
    }
}
