<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Written analyses of what a user consumed in a period, kept so they can be
 * read again without asking the AI (and paying) again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumption_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->text('summary');
            $table->json('findings');
            $table->json('tips');
            $table->json('payload');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumption_analyses');
    }
};
