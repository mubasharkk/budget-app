<?php

namespace Tests\Feature;

use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReceiptListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_defaults_to_fifty_per_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('receipts.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Receipts/Index')
                ->where('filters.per_page', 50)
                ->where('receipts.per_page', 50)
            );
    }

    public function test_index_rejects_invalid_per_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('receipts.index', ['per_page' => 999]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.per_page', 50)
            );
    }

    public function test_index_filters_by_status_and_search(): void
    {
        $user = User::factory()->create();
        Receipt::factory()->for($user)->create(['vendor' => 'REWE', 'status' => 'processed']);
        Receipt::factory()->for($user)->failed()->create(['vendor' => 'ALDI']);

        $this->actingAs($user)
            ->get(route('receipts.index', ['status' => 'failed']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('receipts.total', 1)
                ->where('receipts.data.0.vendor', 'ALDI')
            );

        $this->actingAs($user)
            ->get(route('receipts.index', ['search' => 'rewe']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('receipts.total', 1)
                ->where('receipts.data.0.vendor', 'REWE')
            );
    }

    public function test_index_sorts_by_total_amount(): void
    {
        $user = User::factory()->create();
        Receipt::factory()->for($user)->create(['vendor' => 'Cheap', 'total_amount' => 5]);
        Receipt::factory()->for($user)->create(['vendor' => 'Pricey', 'total_amount' => 500]);

        $this->actingAs($user)
            ->get(route('receipts.index', ['sort' => 'total_amount', 'direction' => 'desc']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('receipts.data.0.vendor', 'Pricey')
            );
    }
}
