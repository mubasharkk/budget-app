<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('source', 30);
            $table->string('external_id', 64);
            $table->string('retailer', 20);
            $table->string('advertiser_name')->nullable();
            $table->string('title');
            $table->string('brand')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('old_price', 10, 2)->nullable();
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->string('unit', 30)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->json('raw');
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index('retailer');
            $table->index('valid_to');
        });

        Schema::create('deal_postal_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('postal_code', 5);
            $table->timestamp('fetched_at');

            $table->unique(['deal_id', 'postal_code']);
            $table->index('postal_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_postal_codes');
        Schema::dropIfExists('deals');
    }
};
