<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * General names ("Milk", "Bananas") that group a user's products for the
 * consumption insights, and the type of each receipt line (a product, or a
 * deposit, bag, discount or fee that is not consumed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumable_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('unit', 10)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('consumable_group_id')->nullable()->after('category_id')
                ->constrained('consumable_groups')->nullOnDelete();
        });

        Schema::table('receipt_items', function (Blueprint $table) {
            $table->string('line_type', 20)->default('product')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('receipt_items', function (Blueprint $table) {
            $table->dropColumn('line_type');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consumable_group_id');
        });

        Schema::dropIfExists('consumable_groups');
    }
};
