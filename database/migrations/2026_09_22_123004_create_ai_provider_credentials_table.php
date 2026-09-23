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
        Schema::create('ai_provider_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider')->unique();
            // Encrypted at rest via `encrypted` casts — must be TEXT (ciphertext is longer than plaintext).
            $table->text('api_key')->nullable();
            $table->text('base_url')->nullable();
            // Driver-specific secrets/settings (Azure deployments, Bedrock AWS creds, ...), encrypted.
            $table->text('extra')->nullable();
            $table->boolean('is_active')->default(true);
            // Last 4 chars of the key for display only — never the full secret.
            $table->string('key_hint')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_provider_credentials');
    }
};
