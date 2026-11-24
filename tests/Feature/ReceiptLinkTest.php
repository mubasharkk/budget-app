<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Receipt;
use App\Models\Saving;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptLinkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    private function searchFor(User $user, string $search = ''): array
    {
        return collect($this->actingAs($user)->getJson(route('receipts.search', ['q' => $search]))->assertOk()->json('receipts'))
            ->pluck('vendor')
            ->all();
    }

    public function test_the_search_finds_the_users_receipts_by_shop_amount_and_date(): void
    {
        $user = User::factory()->create();
        Receipt::factory()->for($user)->create(['vendor' => 'REWE City', 'receipt_date' => '2026-11-03 10:00', 'total_amount' => 23.40]);
        Receipt::factory()->for($user)->create(['vendor' => 'ALDI', 'receipt_date' => '2026-10-15 09:00', 'total_amount' => 23.95]);
        Receipt::factory()->for($user)->create(['vendor' => 'Telekom', 'receipt_date' => '2025-11-03 08:00', 'total_amount' => 39.99]);
        $original = Receipt::factory()->for($user)->create(['vendor' => 'REWE City', 'receipt_date' => '2026-11-01 10:00']);
        Receipt::factory()->for($user)->create(['vendor' => 'REWE City', 'receipt_date' => '2026-11-01 10:00', 'duplicate_of_id' => $original->id]);
        Receipt::factory()->create(['vendor' => 'REWE Someone else']);

        $this->assertSame(['REWE City', 'REWE City', 'ALDI', 'Telekom'], $this->searchFor($user));
        $this->assertSame(['REWE City', 'REWE City'], $this->searchFor($user, 'rewe'));
        $this->assertSame(['REWE City'], $this->searchFor($user, '23,40'));
        $this->assertSame(['REWE City', 'ALDI'], $this->searchFor($user, '23'));
        $this->assertSame(['REWE City', 'Telekom'], $this->searchFor($user, '03.11'));
        $this->assertSame(['REWE City'], $this->searchFor($user, '03.11.2026'));
        $this->assertSame(['REWE City', 'REWE City'], $this->searchFor($user, '11.2026'));
        $this->assertSame(['Telekom'], $this->searchFor($user, '2025'));
        $this->assertSame(['ALDI'], $this->searchFor($user, 'aldi 23'));
        $this->assertSame([], $this->searchFor($user, 'lidl'));
    }

    public function test_a_receipt_attaches_to_a_contract_and_a_savings_entry_and_shows_on_both_pages(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $receipt = Receipt::factory()->for($user)->create(['vendor' => 'Fitness First', 'total_amount' => 29.90]);
        $contract = Contract::factory()->for($user)->create(['name' => 'Gym']);
        $saving = Saving::factory()->for($user)->create(['source' => 'Refund']);

        foreach (['contract' => $contract, 'saving' => $saving] as $type => $record) {
            $this->actingAs($user)->post(route('receipt-links.store'), ['receipt_id' => $receipt->id, 'link_type' => $type, 'link_id' => $record->id])
                ->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->actingAs($user)->post(route('receipt-links.store'), ['receipt_id' => $receipt->id, 'link_type' => 'contract', 'link_id' => $contract->id]);

        $this->assertSame(1, $contract->receipts()->count());
        $this->actingAs($user)->get(route('contracts.show', $contract))
            ->assertInertia(fn ($page) => $page->has('receipts', 1)->where('receipts.0.vendor', 'Fitness First'));
        $this->actingAs($user)->get(route('savings.edit', $saving))
            ->assertInertia(fn ($page) => $page->has('receipts', 1));
        $this->actingAs($user)->get(route('receipts.show', $receipt))
            ->assertInertia(fn ($page) => $page
                ->where('links.0.type', 'contract')->where('links.0.label', 'Gym')
                ->where('links.1.type', 'saving'));

        $this->actingAs($user)->delete(route('receipt-links.destroy'), ['receipt_id' => $receipt->id, 'link_type' => 'contract', 'link_id' => $contract->id])
            ->assertRedirect();
        $this->assertSame(0, $contract->receipts()->count());
        $this->assertSame(1, $saving->receipts()->count());

        $saving->delete();
        $this->assertModelExists($receipt);
        $this->assertDatabaseCount('receipt_links', 0);
    }

    public function test_the_receipt_page_offers_the_users_other_contracts_and_savings_to_attach_to(): void
    {
        $user = User::factory()->create();
        $receipt = Receipt::factory()->for($user)->create();
        $gym = Contract::factory()->for($user)->create(['name' => 'Gym']);
        $internet = Contract::factory()->for($user)->create(['name' => 'Internet']);
        $saving = Saving::factory()->for($user)->create(['source' => 'Refund', 'saved_on' => '2026-11-03']);
        Contract::factory()->create(['name' => 'Someone else']);
        $gym->receipts()->attach($receipt);

        $this->actingAs($user)->get(route('receipts.show', $receipt))
            ->assertInertia(fn ($page) => $page
                ->where('attachOptions.0.type', 'contract')
                ->where('attachOptions.0.options', [['id' => $internet->id, 'label' => 'Internet']])
                ->where('attachOptions.1.type', 'saving')
                ->where('attachOptions.1.options', [['id' => $saving->id, 'label' => 'Refund · 03.11.2026']]));
    }

    public function test_only_the_users_own_receipts_and_records_can_be_attached(): void
    {
        $user = User::factory()->create();
        $receipt = Receipt::factory()->for($user)->create();
        $contract = Contract::factory()->for($user)->create();

        $this->actingAs($user)->post(route('receipt-links.store'), ['receipt_id' => Receipt::factory()->create()->id, 'link_type' => 'contract', 'link_id' => $contract->id])
            ->assertSessionHasErrors('receipt_id');
        $this->actingAs($user)->post(route('receipt-links.store'), ['receipt_id' => $receipt->id, 'link_type' => 'contract', 'link_id' => Contract::factory()->create()->id])
            ->assertSessionHasErrors('link_id');
        $this->actingAs($user)->post(route('receipt-links.store'), ['receipt_id' => $receipt->id, 'link_type' => 'budget', 'link_id' => 1])
            ->assertSessionHasErrors('link_type');

        $this->assertDatabaseCount('receipt_links', 0);
    }

    public function test_deleting_a_receipt_removes_its_links(): void
    {
        $user = User::factory()->create();
        $receipt = Receipt::factory()->for($user)->create();
        $contract = Contract::factory()->for($user)->create();
        $contract->receipts()->attach($receipt);

        $receipt->delete();

        $this->assertModelExists($contract);
        $this->assertDatabaseCount('receipt_links', 0);
    }

    public function test_contract_invoices_are_stored_shown_and_removed_by_the_owner_only(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $contract = Contract::factory()->for($user)->create();

        $this->actingAs($user)->post(route('contracts.invoices.store', $contract), [
            'files' => [UploadedFile::fake()->create('invoice-2026-11.pdf', 120, 'application/pdf')],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('contracts.invoices.store', $contract), ['files' => [UploadedFile::fake()->create('notes.txt', 1)]])
            ->assertSessionHasErrors('files.0');

        $invoice = $contract->invoices()->sole();
        $this->actingAs($user)->get(route('contracts.show', $contract))
            ->assertInertia(fn ($page) => $page->has('invoices', 1)->where('invoices.0.name', 'invoice-2026-11.pdf'));
        $this->actingAs($user)->get(route('contracts.invoices.show', [$contract, $invoice]))->assertOk();

        $intruder = User::factory()->create();
        $this->actingAs($intruder)->get(route('contracts.invoices.show', [$contract, $invoice]))->assertForbidden();
        $this->actingAs($intruder)->delete(route('contracts.invoices.destroy', [$contract, $invoice]))->assertForbidden();
        $this->actingAs($intruder)->post(route('contracts.invoices.store', $contract), ['files' => [UploadedFile::fake()->create('x.pdf', 1, 'application/pdf')]])
            ->assertForbidden();
        $otherContract = Contract::factory()->for($user)->create();
        $this->actingAs($user)->get(route('contracts.invoices.show', [$otherContract, $invoice]))->assertNotFound();

        $this->actingAs($user)->delete(route('contracts.invoices.destroy', [$contract, $invoice]))->assertRedirect();
        $this->assertSame(0, $contract->invoices()->count());
    }
}
