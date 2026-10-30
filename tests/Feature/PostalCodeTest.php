<?php

namespace Tests\Feature;

use App\Jobs\SyncDealsForPostalCode;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PostalCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_new_users_have_no_postal_code(): void
    {
        $this->assertNull(User::factory()->create()->fresh()->postal_code);
    }

    public function test_user_can_save_a_postal_code_and_is_sent_back_to_the_page_that_asked(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('deals'))
            ->patch(route('profile.postal-code.update'), ['postal_code' => ' 10115 '])
            ->assertRedirect(route('deals'));

        $this->assertSame('10115', $user->fresh()->postal_code);
        Queue::assertPushed(SyncDealsForPostalCode::class, fn (SyncDealsForPostalCode $job): bool => $job->postalCode === '10115');
    }

    public function test_no_sync_is_queued_when_deals_for_the_postal_code_are_already_stored(): void
    {
        Deal::factory()->availableIn('10115')->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.postal-code.update'), ['postal_code' => '10115'])
            ->assertSessionHasNoErrors();

        Queue::assertNotPushed(SyncDealsForPostalCode::class);
    }

    public function test_postal_code_is_shared_with_the_frontend(): void
    {
        $user = User::factory()->withPostalCode('31139')->create();

        $this->actingAs($user)
            ->get(route('deals'))
            ->assertInertia(fn ($page) => $page
                ->component('Deals')
                ->where('auth.user.postal_code', '31139')
                ->has('retailers', 5));
    }

    public function test_user_can_change_an_existing_postal_code(): void
    {
        $user = User::factory()->withPostalCode('10115')->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.postal-code.update'), ['postal_code' => '80331'])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('80331', $user->fresh()->postal_code);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidPostalCodes(): array
    {
        return [
            'missing' => [''],
            'too short' => ['1011'],
            'too long' => ['101155'],
            'letters' => ['1O115'],
            'spaces inside' => ['10 115'],
        ];
    }

    #[DataProvider('invalidPostalCodes')]
    public function test_invalid_postal_codes_are_rejected(mixed $postalCode): void
    {
        $user = User::factory()->withPostalCode('10115')->create();

        $this->actingAs($user)
            ->patch(route('profile.postal-code.update'), ['postal_code' => $postalCode])
            ->assertSessionHasErrors('postal_code');

        $this->assertSame('10115', $user->fresh()->postal_code);
        Queue::assertNothingPushed();
    }

    public function test_guests_cannot_save_a_postal_code(): void
    {
        $this->patch(route('profile.postal-code.update'), ['postal_code' => '10115'])
            ->assertRedirect(route('login'));
    }
}
