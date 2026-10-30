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
        Schema::table('buildings', function (Blueprint $table) {
            $table->decimal('forecast_peak_threshold_percent', 5, 2)->default(15);
            $table->decimal('bill_change_threshold_percent', 5, 2)->default(10);
            $table->decimal('coverage_alert_threshold_percent', 5, 2)->default(75);
            $table->unsignedSmallInteger('stale_after_hours')->default(48);
            $table->boolean('alerts_enabled')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn([
                'forecast_peak_threshold_percent',
                'bill_change_threshold_percent',
                'coverage_alert_threshold_percent',
                'stale_after_hours',
                'alerts_enabled',
            ]);
        });
    }
};
