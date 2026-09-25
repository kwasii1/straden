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
        Schema::create('repository_test', function (Blueprint $table) {
            $table->foreignUuid('test_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('repository_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['test_id', 'repository_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repository_test');
    }
};
