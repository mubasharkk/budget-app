<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Receipts attached to other records (contracts, savings entries). The
     * record is stored by its model class, as tasks are (no morph map).
     */
    public function up(): void
    {
        Schema::create('receipt_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->string('linkable_type');
            $table->unsignedBigInteger('linkable_id');
            $table->timestamps();

            $table->unique(['receipt_id', 'linkable_type', 'linkable_id']);
            $table->index(['linkable_type', 'linkable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_links');
    }
};
