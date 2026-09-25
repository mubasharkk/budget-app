<?php

namespace Tests\Feature;

use App\Domain\Identity\Services\GoogleAccountService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAccountServiceTest extends TestCase
{
    use RefreshDatabase;

    private function googleUser(string $id, string $email): SocialiteUser
    {
        return (new SocialiteUser)->map([
            'id' => $id,
            'name' => 'Jamie Doe',
            'email' => $email,
            'avatar' => 'https://example.com/avatar.png',
        ]);
    }

    public function test_returns_the_user_already_linked_to_the_google_account(): void
    {
        $linked = User::factory()->create(['google_id' => 'g-1']);

        $resolved = app(GoogleAccountService::class)->resolveUser($this->googleUser('g-1', 'other@example.com'));

        $this->assertTrue($resolved->is($linked));
    }

    public function test_links_an_existing_account_with_the_same_email(): void
    {
        $existing = User::factory()->create(['email' => 'jamie@example.com', 'google_id' => null]);

        $resolved = app(GoogleAccountService::class)->resolveUser($this->googleUser('g-2', 'jamie@example.com'));

        $this->assertTrue($resolved->is($existing));
        $this->assertSame('g-2', $existing->fresh()->google_id);
        $this->assertSame(1, User::query()->count());
    }

    public function test_registers_a_new_verified_user(): void
    {
        $resolved = app(GoogleAccountService::class)->resolveUser($this->googleUser('g-3', 'new@example.com'));

        $this->assertSame('new@example.com', $resolved->email);
        $this->assertSame('g-3', $resolved->google_id);
        $this->assertNotNull($resolved->email_verified_at);
    }
}
