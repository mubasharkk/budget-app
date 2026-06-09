<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('monthly_income', 12, 2)->nullable()->after('avatar');
            $table->string('income_type')->nullable()->after('monthly_income');
            $table->string('income_currency', 3)->default('EUR')->after('income_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['monthly_income', 'income_type', 'income_currency']);
        });
    }
};
