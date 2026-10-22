<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_pelanggan_can_update_name_and_phone_but_not_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/profil', ['nama' => 'Nama Baru', 'no_hp' => '081299998888', 'role' => 'SUPER_ADMIN', 'email' => 'lain@contoh.test'])
            ->assertRedirect('/profil')
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertSame('Nama Baru', $user->nama);
        $this->assertSame('081299998888', $user->no_hp);
        $this->assertNotSame('lain@contoh.test', $user->email);
        $this->assertSame('PELANGGAN', $user->role->value);
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/profil/password', ['password_lama' => 'salah', 'password' => 'passwordbaru', 'password_confirmation' => 'passwordbaru'])
            ->assertSessionHasErrors('password_lama');

        $this->actingAs($user)
            ->put('/profil/password', ['password_lama' => 'password', 'password' => 'passwordbaru', 'password_confirmation' => 'passwordbaru'])
            ->assertRedirect('/profil');

        $this->assertTrue(password_verify('passwordbaru', $user->fresh()->password_hash));
    }
}
