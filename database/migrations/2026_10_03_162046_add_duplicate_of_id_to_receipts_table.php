<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->foreignId('duplicate_of_id')->nullable()->after('kind')
                ->constrained('receipts')->nullOnDelete();
            $table->timestamp('duplicate_ignored_at')->nullable()->after('duplicate_of_id');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('duplicate_of_id');
            $table->dropColumn('duplicate_ignored_at');
        });
    }
};
