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
            $table->string('file_path'); // Will be renamed to 'original_path' by modify migration
            $table->string('file_type')->nullable();
            $table->string('mime_type'); // Will be renamed to 'mime' by modify migration
            $table->bigInteger('file_size')->nullable();
            $table->longText('extracted_text')->nullable(); // Will be renamed to 'ocr_text' by modify migration
            $table->json('ocr_data')->nullable();
            $table->datetime('receipt_date')->nullable();
            $table->string('receipt_timezone')->nullable();
            $table->enum('status', ['uploaded', 'processing', 'processed', 'failed'])->default('uploaded'); // Will be changed by modify migration
            $table->text('error_message')->nullable();
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