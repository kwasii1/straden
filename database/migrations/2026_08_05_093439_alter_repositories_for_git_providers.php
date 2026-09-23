<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->dropColumn(['git_auth_type', 'git_credentials']);

            $table->foreignUuid('connector_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name')->nullable();
            $table->timestamp('cloned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->dropForeign(['connector_id']);
            $table->dropColumn(['connector_id', 'full_name', 'cloned_at']);

            $table->string('git_auth_type')->nullable();
            $table->text('git_credentials')->nullable();
        });
    }
};
