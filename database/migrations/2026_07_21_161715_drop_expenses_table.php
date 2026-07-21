<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The one-time expense ledger duplicated the receipt feature (same fields, same
 * media collection, same vision parser) without feeding any dashboard totals.
 * It is removed in favour of receipts.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('media')->where('model_type', 'App\Models\Expense')->delete();

        Schema::dropIfExists('expenses');
    }

    public function down(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->date('spent_on');
            $table->string('description')->nullable();
            $table->string('expense_type')->default('personal');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'spent_on']);
        });
    }
};
