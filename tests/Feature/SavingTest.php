<?php

namespace Tests\Feature;

use App\Models\Saving;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_user_can_record_savings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('savings.store'), [
                'amount' => 500,
                'currency' => 'EUR',
                'saved_on' => '2026-06-01',
                'source' => 'Emergency fund',
                'notes' => 'Monthly transfer',
            ])
            ->assertRedirect(route('savings.index'));

        $this->assertDatabaseHas('savings', [
            'user_id' => $user->id,
            'amount' => 500,
            'source' => 'Emergency fund',
        ]);
    }

    public function test_index_only_shows_the_users_own_savings_entries(): void
    {
        $user = User::factory()->create();
        Saving::factory()->for($user)->create(['source' => 'Mine']);
        Saving::factory()->create(['source' => 'Theirs']);

        $this->actingAs($user)
            ->get(route('savings.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Savings/Index')
                ->has('savings', 1)
                ->where('savings.0.source', 'Mine')
            );
    }

    public function test_non_owner_cannot_update_or_delete_savings(): void
    {
        $saving = Saving::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->put(route('savings.update', $saving), [
                'amount' => 1,
                'currency' => 'EUR',
                'saved_on' => '2026-06-01',
            ])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('savings.destroy', $saving))
            ->assertForbidden();
    }
}
