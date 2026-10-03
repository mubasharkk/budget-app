<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Income;
use App\Models\Saving;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Create, edit, update and delete pages of the user-owned resources, and that
 * another user can reach none of them.
 */
class OwnedResourcePagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: \Closure(User): Model, 3: \Closure(): array<string, mixed>, 4: string, 5: mixed}>
     */
    public static function resources(): array
    {
        return [
            'budgets' => [
                'budgets', 'Budgets',
                fn (User $user) => Budget::factory()->for($user)->create(['period' => 'monthly']),
                fn () => ['category_id' => Category::factory()->create()->id, 'period' => 'monthly', 'amount' => 321, 'currency' => 'EUR', 'starts_on' => '2026-06-01'],
                'amount', '321.00',
            ],
            'contracts' => [
                'contracts', 'Contracts',
                fn (User $user) => Contract::factory()->for($user)->create(),
                fn () => ['name' => 'Fibre 1000', 'amount' => 49.99, 'currency' => 'EUR', 'billing_cycle' => 'monthly', 'start_date' => '2026-01-01', 'status' => 'active', 'auto_renew' => true],
                'name', 'Fibre 1000',
            ],
            'savings' => [
                'savings', 'Savings',
                fn (User $user) => Saving::factory()->for($user)->create(),
                fn () => ['amount' => 750, 'currency' => 'EUR', 'saved_on' => '2026-06-01', 'source' => 'Holiday pot'],
                'source', 'Holiday pot',
            ],
            'incomes' => [
                'incomes', 'Incomes',
                fn (User $user) => Income::factory()->for($user)->create(),
                fn () => ['amount' => 1800, 'currency' => 'EUR', 'received_on' => '2026-06-01', 'source' => 'Bonus', 'income_type' => 'net'],
                'source', 'Bonus',
            ],
        ];
    }

    /**
     * @param  \Closure(User): Model  $makeRecord
     * @param  \Closure(): array<string, mixed>  $payload
     */
    #[DataProvider('resources')]
    public function test_owner_can_open_forms_update_and_delete(string $route, string $component, \Closure $makeRecord, \Closure $payload, string $field, mixed $expected): void
    {
        $user = User::factory()->create();
        $record = $makeRecord($user);
        $this->actingAs($user);

        $this->get(route("{$route}.create"))->assertInertia(fn ($page) => $page->component("{$component}/Create"));
        $this->get(route("{$route}.edit", $record))->assertInertia(fn ($page) => $page->component("{$component}/Edit"));

        $this->put(route("{$route}.update", $record), $payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertSame($expected, (string) $record->fresh()->{$field});

        $this->delete(route("{$route}.destroy", $record))->assertRedirect();
        $this->assertModelMissing($record);
    }

    /**
     * @param  \Closure(User): Model  $makeRecord
     * @param  \Closure(): array<string, mixed>  $payload
     */
    #[DataProvider('resources')]
    public function test_other_users_are_forbidden(string $route, string $component, \Closure $makeRecord, \Closure $payload): void
    {
        $record = $makeRecord(User::factory()->create());
        $this->actingAs(User::factory()->create());

        $this->get(route("{$route}.edit", $record))->assertForbidden();
        $this->put(route("{$route}.update", $record), $payload())->assertForbidden();
        $this->delete(route("{$route}.destroy", $record))->assertForbidden();
        $this->assertModelExists($record);
    }

    public function test_contract_detail_page_is_owner_only(): void
    {
        $contract = Contract::factory()->create();

        $this->actingAs($contract->user)
            ->get(route('contracts.show', $contract))
            ->assertInertia(fn ($page) => $page->component('Contracts/Show')->where('contract.id', $contract->id));

        $this->actingAs(User::factory()->create())
            ->get(route('contracts.show', $contract))
            ->assertForbidden();
    }
}
