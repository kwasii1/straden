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
        Schema::create('repositories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('type');

            // Git-based repos
            $table->string('git_url')->nullable();
            $table->string('git_branch')->default('main');
            $table->string('git_auth_type')->nullable();
            $table->text('git_credentials')->nullable();

            // Local folder mount
            $table->string('local_path')->nullable();

            // Indexing/sync state for AI context
            $table->string('sync_status')->default('pending');
            $table->text('sync_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_commit_sha')->nullable();

            // Cached file tree for browsing
            $table->json('file_tree')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
