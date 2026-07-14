<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 cleanup: remove the legacy public-disk path columns now that receipt
 * files live in media-library. Only apply this after receipts:migrate-to-media-library
 * has run and been verified in the target environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->dropColumn(['original_path', 'stored_path']);
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->string('original_path')->nullable()->after('original_filename');
            $table->string('stored_path')->nullable()->after('original_path');
        });
    }
};
