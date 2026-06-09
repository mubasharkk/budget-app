<?php

namespace Tests\Feature;

use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_user_can_record_one_time_income(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('incomes.store'), [
                'amount' => 1500,
                'currency' => 'EUR',
                'received_on' => '2026-06-01',
                'source' => 'Freelance project',
                'income_type' => 'net',
                'notes' => 'June invoice',
            ])
            ->assertRedirect(route('incomes.index'));

        $this->assertDatabaseHas('incomes', [
            'user_id' => $user->id,
            'amount' => 1500,
            'source' => 'Freelance project',
        ]);
    }

    public function test_index_only_shows_the_users_own_income_entries(): void
    {
        $user = User::factory()->create();
        Income::factory()->for($user)->create(['source' => 'Mine']);
        Income::factory()->create(['source' => 'Theirs']);

        $this->actingAs($user)
            ->get(route('incomes.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Incomes/Index')
                ->has('incomes', 1)
                ->where('incomes.0.source', 'Mine')
            );
    }

    public function test_non_owner_cannot_update_or_delete_income(): void
    {
        $income = Income::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->put(route('incomes.update', $income), [
                'amount' => 1,
                'currency' => 'EUR',
                'received_on' => '2026-06-01',
            ])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('incomes.destroy', $income))
            ->assertForbidden();
    }
}
