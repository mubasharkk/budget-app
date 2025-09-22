<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('original_filename');
            $table->string('original_path');
            $table->string('stored_path')->nullable();
            $table->string('file_type')->nullable();
            $table->string('mime');
            $table->bigInteger('file_size')->nullable();
            $table->longText('ocr_text')->nullable();
            $table->json('ocr_data')->nullable();
            $table->datetime('receipt_date')->nullable();
            $table->string('receipt_timezone')->nullable();
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->string('vendor')->nullable();
            $table->string('currency', 3)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['user_id', 'status']);
            $table->index('receipt_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
