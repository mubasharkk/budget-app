<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('retailer')->constrained('categories')->nullOnDelete();
            $table->decimal('unit_price', 10, 2)->nullable()->after('unit');
            $table->string('unit_price_unit', 10)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['unit_price', 'unit_price_unit']);
        });
    }
};
