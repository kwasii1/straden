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
        Schema::create('run_insights', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->constrained()->cascadeOnDelete();

            // enum: queued, generating, completed, failed
            $table->string('status')->default('queued');

            // Structured report produced by RunInsightAgent.
            $table->json('report')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['run_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('run_insights');
    }
};
