<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\GoogleController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_login_sets_a_thirty_day_remember_cookie(): void
    {
        $user = User::factory()->create(['google_id' => 'g-1']);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn((new SocialiteUser)->map([
            'id' => 'g-1', 'name' => $user->name, 'email' => $user->email, 'avatar' => null,
        ]));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $cookie = $response->getCookie(Auth::guard('web')->getRecallerName(), false);
        $this->assertNotNull($cookie);
        $this->assertEqualsWithDelta(
            now()->addMinutes(GoogleController::REMEMBER_MINUTES)->getTimestamp(),
            $cookie->getExpiresTime(),
            5,
        );
    }
}
