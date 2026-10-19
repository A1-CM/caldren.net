<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forecast_runs', function (Blueprint $table) {
            $table->decimal('historical_peak_kw', 14, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('forecast_runs', function (Blueprint $table) {
            $table->dropColumn('historical_peak_kw');
        });
    }
};
