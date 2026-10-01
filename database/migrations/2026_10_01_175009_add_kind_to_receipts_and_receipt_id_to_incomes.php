<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('kind', 20)->default('expense')->after('expense_type');
            $table->index(['user_id', 'kind']);
        });

        Schema::table('incomes', function (Blueprint $table) {
            $table->foreignId('receipt_id')->nullable()->unique()->after('user_id')
                ->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('receipt_id');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
