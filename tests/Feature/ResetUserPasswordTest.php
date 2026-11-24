<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetUserPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_a_new_password_by_email_or_id(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.com']);

        $this->artisan('user:reset-password', ['user' => 'anna@example.com', '--password' => 'new-secret-123'])
            ->expectsOutput('Password reset for anna@example.com. They are signed out everywhere and their API tokens are revoked.')
            ->assertSuccessful();
        $this->assertTrue(Hash::check('new-secret-123', $user->fresh()->password));

        $this->artisan('user:reset-password', ['user' => (string) $user->id])
            ->expectsQuestion('New password', 'other-secret-456')
            ->expectsQuestion('Repeat the new password', 'other-secret-456')
            ->assertSuccessful();
        $this->assertTrue(Hash::check('other-secret-456', $user->fresh()->password));
    }

    public function test_it_signs_the_user_out_of_open_sessions_and_revokes_api_tokens(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.com']);
        $user->createToken('phone');
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->artisan('user:reset-password', ['user' => 'anna@example.com', '--password' => 'new-secret-123'])->assertSuccessful();

        $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_it_refuses_unknown_users_mismatches_and_short_passwords(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.com']);
        $original = $user->password;

        $this->artisan('user:reset-password', ['user' => 'nobody@example.com', '--password' => 'new-secret-123'])
            ->expectsOutput('No user found for "nobody@example.com".')
            ->assertFailed();

        $this->artisan('user:reset-password', ['user' => 'anna@example.com'])
            ->expectsQuestion('New password', 'new-secret-123')
            ->expectsQuestion('Repeat the new password', 'different-123')
            ->expectsOutput('The passwords do not match.')
            ->assertFailed();

        $this->artisan('user:reset-password', ['user' => 'anna@example.com', '--password' => 'short'])
            ->assertFailed();

        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_it_can_generate_a_password(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.com']);

        $this->artisan('user:reset-password', ['user' => 'anna@example.com', '--generate' => true])
            ->expectsOutputToContain('New password: ')
            ->assertSuccessful();

        $this->assertFalse(Hash::check('password', $user->fresh()->password));
    }
}
