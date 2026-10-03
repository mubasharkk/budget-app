<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_a_token_that_identifies_the_user_until_logout(): void
    {
        $user = User::factory()->create(['email' => 'app@example.com']);

        $token = $this->postJson('/api/login', [
            'email' => 'app@example.com',
            'password' => 'password',
            'device_name' => 'iPhone',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->json('token');

        $this->assertSame('iPhone', $user->tokens()->sole()->name);
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJson(['id' => $user->id, 'email' => 'app@example.com']);

        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_login_rejects_wrong_credentials_and_missing_fields(): void
    {
        User::factory()->create(['email' => 'app@example.com']);

        $this->postJson('/api/login', ['email' => 'app@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The provided credentials are incorrect.']);

        $this->postJson('/api/login', [])
            ->assertJsonValidationErrors([
                'email' => 'An email address is required to sign in.',
                'password' => 'A password is required to sign in.',
            ]);
    }
}
