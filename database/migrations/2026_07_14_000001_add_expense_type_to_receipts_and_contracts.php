<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->string('expense_type')->default('personal')->after('currency');
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('expense_type')->default('personal')->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->dropColumn('expense_type');
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn('expense_type');
        });
    }
};
