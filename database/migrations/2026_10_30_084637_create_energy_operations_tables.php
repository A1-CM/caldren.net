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
        Schema::create('daily_energy_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->date('summary_date');
            $table->decimal('kwh', 16, 4)->default(0);
            $table->decimal('peak_kw', 16, 4)->default(0);
            $table->unsignedSmallInteger('interval_minutes')->nullable();
            $table->unsignedInteger('reading_count')->default(0);
            $table->unsignedInteger('expected_count')->default(0);
            $table->decimal('coverage_percent', 5, 2)->default(0);
            $table->timestamp('first_reading_at')->nullable();
            $table->timestamp('last_reading_at')->nullable();
            $table->timestamps();
            $table->unique(['building_id', 'summary_date']);
            $table->index('summary_date');
        });

        Schema::create('data_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->string('status', 20)->default('processing');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('inserted_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->string('failure_reason', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['building_id', 'created_at']);
        });

        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recommendation_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('manual');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 12)->default('medium');
            $table->string('status', 20)->default('new');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('opportunity_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('operational_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('forecast_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('severity', 12)->default('medium');
            $table->string('title');
            $table->text('message');
            $table->string('fingerprint');
            $table->string('status', 20)->default('open');
            $table->timestamp('detected_at');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'fingerprint']);
            $table->index(['organization_id', 'status', 'severity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_alerts');
        Schema::dropIfExists('opportunity_activities');
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('data_imports');
        Schema::dropIfExists('daily_energy_summaries');
    }
};
