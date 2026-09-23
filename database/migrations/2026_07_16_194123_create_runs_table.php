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
            // enum: queued, running, passed, failed, error, aborted

            $table->string('triggered_by')->default('manual');
            // enum: manual, scheduled, api

            $table->foreignUuid('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            // k6 summary metrics (denormalized from summary JSON — avoids re-querying InfluxDB for the list/card view)
            $table->unsignedInteger('vus_max')->nullable();
            $table->unsignedBigInteger('requests_total')->nullable();
            $table->decimal('requests_per_second', 10, 2)->nullable();
            $table->decimal('req_duration_p95_ms', 10, 2)->nullable();
            $table->decimal('req_duration_p99_ms', 10, 2)->nullable();
            $table->decimal('error_rate', 5, 2)->nullable();
            $table->unsignedInteger('checks_total')->nullable();
            $table->unsignedInteger('checks_failed')->nullable();

            $table->boolean('thresholds_passed')->nullable();
            $table->json('thresholds_summary')->nullable();
            // [{name, threshold, actual, passed}, ...] — avoids a separate table for v1

            $table->json('run_config')->nullable();
            // stages/VUs/duration snapshot at execution time, script git commit if repo-linked

            $table->string('k6_container_id')->nullable();
            $table->integer('exit_code')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['script_id', 'status']);
            $table->index('started_at');
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
