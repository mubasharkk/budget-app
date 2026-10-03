<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contract;
use App\Models\Permission;
use App\Models\Provider;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every Backpack CRUD screen renders for an admin: list, its AJAX table data,
 * create, edit and (where enabled) show.
 */
class AdminCrudSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: \Closure(): Model}>
     */
    public static function cruds(): array
    {
        return [
            'users' => ['user', fn () => User::factory()->create()],
            'categories' => ['category', fn () => Category::factory()->create()],
            'receipts' => ['receipt', fn () => Receipt::factory()->create()],
            'receipt items' => ['receipt-item', fn () => ReceiptItem::factory()->create()],
            'roles' => ['role', fn () => Role::create(['name' => 'editor', 'guard_name' => 'web'])],
            'permissions' => ['permission', fn () => Permission::create(['name' => 'edit receipts', 'guard_name' => 'web'])],
            'providers' => ['provider', fn () => Provider::factory()->create()],
            'contracts' => ['contract', fn () => Contract::factory()->create()],
        ];
    }

    /**
     * One request per test: Backpack configures its CRUD panel once per application
     * instance, so a second request in the same test would reuse the first screen's setup.
     *
     * @return array<string, array{0: string, 1: \Closure(): Model, 2: string}>
     */
    public static function screens(): array
    {
        $screens = [];

        foreach (self::cruds() as $name => [$entity, $makeRecord]) {
            foreach (['list', 'table data', 'create', 'edit', 'show'] as $screen) {
                $screens["{$name} {$screen}"] = [$entity, $makeRecord, $screen];
            }
        }

        return $screens;
    }

    /**
     * @param  \Closure(): Model  $makeRecord
     */
    #[DataProvider('screens')]
    public function test_admin_crud_screen_renders(string $entity, \Closure $makeRecord, string $screen): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $key = $makeRecord()->getKey();

        $this->actingAs($admin, 'backpack');

        $response = match ($screen) {
            'list' => $this->get("/admin/{$entity}"),
            'table data' => $this->postJson("/admin/{$entity}/search", ['draw' => 1, 'start' => 0, 'length' => 10]),
            'create' => $this->get("/admin/{$entity}/create"),
            'edit' => $this->get("/admin/{$entity}/{$key}/edit"),
            'show' => $this->get("/admin/{$entity}/{$key}/show"),
        };

        $response->assertOk();

        if ($screen === 'table data') {
            $this->assertGreaterThanOrEqual(1, $response->json('recordsTotal'));
        }
    }
}
