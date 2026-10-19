<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forecast_runs', function (Blueprint $table): void {
            $table->string('source', 32)->default('openai');
            $table->string('model_version', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('forecast_runs', function (Blueprint $table): void {
            $table->dropColumn(['source', 'model_version']);
        });
    }
};
