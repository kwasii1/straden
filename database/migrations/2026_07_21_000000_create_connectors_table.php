<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connectors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->boolean('is_system')->default(false);
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('database')->nullable();
            $table->boolean('ssl_enabled')->default(false);
            $table->boolean('verify_ssl')->default(false);
            $table->unsignedInteger('timeout')->default(5);
            $table->text('username')->nullable();
            $table->text('password')->nullable();
            $table->text('token')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_successful')->nullable();
            $table->text('last_test_error')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connectors');
    }
};
