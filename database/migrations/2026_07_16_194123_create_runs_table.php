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
        Schema::create('runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('script_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->string('script_name_snapshot');
            $table->string('script_snapshot_path'); // immutable copy, not DB text
            $table->json('config_snapshot')->nullable();
            $table->string('container_id')->nullable();
            $table->string('influxdb_database')->nullable();
            $table->json('summary_metrics')->nullable();
            $table->string('ai_analysis_path')->nullable(); // narrative can get long too — file it
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['script_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runs');
    }
};
