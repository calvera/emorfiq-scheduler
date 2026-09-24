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
        Schema::create('scheduler_locks', function (Blueprint $table) {
            $table->string('key', 191)->primary();
            $table->string('owner', 64);
            $table->dateTime('acquired_at');
            $table->dateTime('expires_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduler_locks');
    }
};
