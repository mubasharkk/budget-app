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
        Schema::table('receipts', function (Blueprint $table) {
            // Add new fields for ReceiptManager
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->string('vendor')->nullable();
            $table->string('currency', 3)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            
            // Rename existing fields to match our spec
            $table->renameColumn('extracted_text', 'ocr_text');
            $table->renameColumn('file_path', 'original_path');
            $table->renameColumn('mime_type', 'mime');
            
            // Update status enum to match our spec
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            // Remove added fields
            $table->dropForeign(['category_id']);
            $table->dropForeign(['subcategory_id']);
            $table->dropColumn(['category_id', 'subcategory_id', 'vendor', 'currency', 'total_amount']);
            
            // Rename back to original names
            $table->renameColumn('ocr_text', 'extracted_text');
            $table->renameColumn('original_path', 'file_path');
            $table->renameColumn('mime', 'mime_type');
            
            // Revert status enum
            $table->enum('status', ['uploaded', 'processing', 'processed', 'failed'])->default('uploaded')->change();
        });
    }
};
