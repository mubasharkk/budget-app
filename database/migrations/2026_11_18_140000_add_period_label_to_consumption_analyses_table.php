<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumption_analyses', function (Blueprint $table) {
            $table->string('period_label', 50)->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('consumption_analyses', function (Blueprint $table) {
            $table->dropColumn('period_label');
        });
    }
};
