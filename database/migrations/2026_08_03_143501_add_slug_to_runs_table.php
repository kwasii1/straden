<?php

use App\Models\Run;
use App\Services\SlugGenerator;
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
        Schema::table('runs', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('id');
        });

        Run::with('script')->eachById(function (Run $run) {
            $prefix = $run->script?->name ?? 'run';
            $run->slug = SlugGenerator::unique($prefix.' '.$run->created_at->format('YmdHis'), Run::class);
            $run->save();
        });

        Schema::table('runs', function (Blueprint $table) {
            $table->string('slug')->unique()->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
