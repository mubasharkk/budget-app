<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    public function test_user_can_attach_a_document_when_recording_an_expense(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('expenses.store'), [
                'amount' => 42.50,
                'currency' => 'EUR',
                'spent_on' => '2026-06-01',
                'expense_type' => 'business',
                'document' => UploadedFile::fake()->create('invoice.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('expenses.index'));

        $expense = Expense::first();
        $media = $expense->getFirstMedia(Expense::DOCUMENT_COLLECTION);

        $this->assertNotNull($media);
        $this->assertSame('invoice.pdf', $media->file_name);
    }

    public function test_owner_can_download_document_but_others_cannot(): void
    {
        $user = User::factory()->create();
        $expense = Expense::factory()->for($user)->create();
        $expense->addMedia(UploadedFile::fake()->create('invoice.pdf', 120, 'application/pdf'))
            ->toMediaCollection(Expense::DOCUMENT_COLLECTION);

        $this->actingAs($user)
            ->get(route('expenses.document', $expense))
            ->assertOk();

        $intruder = User::factory()->create();
        $this->actingAs($intruder)
            ->get(route('expenses.document', $expense))
            ->assertForbidden();
    }

    public function test_document_route_returns_404_when_none_attached(): void
    {
        $user = User::factory()->create();
        $expense = Expense::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('expenses.document', $expense))
            ->assertNotFound();
    }

    public function test_updating_with_remove_document_clears_the_attachment(): void
    {
        $user = User::factory()->create();
        $expense = Expense::factory()->for($user)->create();
        $expense->addMedia(UploadedFile::fake()->create('invoice.pdf', 120, 'application/pdf'))
            ->toMediaCollection(Expense::DOCUMENT_COLLECTION);

        $this->actingAs($user)
            ->put(route('expenses.update', $expense), [
                'amount' => $expense->amount,
                'currency' => $expense->currency,
                'spent_on' => $expense->spent_on->toDateString(),
                'expense_type' => $expense->expense_type->value,
                'remove_document' => true,
            ])
            ->assertRedirect(route('expenses.index'));

        $this->assertNull($expense->fresh()->getFirstMedia(Expense::DOCUMENT_COLLECTION));
    }
}
