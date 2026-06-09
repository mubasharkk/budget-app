<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('receipt_item_id')->nullable()->unique()->constrained('receipt_items')->nullOnDelete();
            $table->string('vendor')->nullable();
            $table->decimal('unit_price', 12, 4);
            $table->string('currency', 3)->default('EUR');
            $table->date('observed_at');
            $table->timestamps();

            $table->index(['product_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_observations');
    }
};
