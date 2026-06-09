<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('normalized_name');
            $table->string('brand')->nullable();
            $table->string('unit')->nullable();
            $table->string('size')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'normalized_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
