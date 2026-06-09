<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_own_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('products.show', $product))
            ->assertOk();
    }

    public function test_user_cannot_view_another_users_product(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('products.show', $product))
            ->assertForbidden();
    }
}
