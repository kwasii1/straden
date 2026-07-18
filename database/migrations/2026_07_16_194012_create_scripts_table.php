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
        Schema::create('scripts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('test_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('disk')->default('local');           // storage disk name
            $table->string('script_path')->nullable();          // e.g. scripts/{test_id}/{script_id}/script.js
            $table->string('ai_generated_script_path')->nullable(); // last AI draft, kept separate from edited version
            $table->json('config')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scripts');
    }
};
