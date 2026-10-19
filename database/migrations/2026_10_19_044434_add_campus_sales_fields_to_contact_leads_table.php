<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_leads', function (Blueprint $table) {
            $table->string('inquiry_type', 30)->default('general')->after('company');
            $table->unsignedInteger('estimated_building_count')->nullable()->after('inquiry_type');
            $table->text('operating_regions')->nullable()->after('estimated_building_count');
            $table->string('available_energy_data', 40)->nullable()->after('operating_regions');
            $table->text('goals')->nullable()->after('available_energy_data');
            $table->string('target_timeline', 40)->nullable()->after('goals');
        });
    }

    public function down(): void
    {
        Schema::table('contact_leads', function (Blueprint $table) {
            $table->dropColumn([
                'inquiry_type',
                'estimated_building_count',
                'operating_regions',
                'available_energy_data',
                'goals',
                'target_timeline',
            ]);
        });
    }
};
