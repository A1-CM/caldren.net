<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamp('trial_ends_at');
            $table->timestamps();
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'email']);
        });

        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('currency', 3)->default('USD');
            $table->decimal('energy_rate', 12, 4)->default(0);
            $table->decimal('peak_rate', 12, 4)->nullable();
            $table->unsignedTinyInteger('peak_start_hour')->nullable();
            $table->unsignedTinyInteger('peak_end_hour')->nullable();
            $table->timestamps();
        });

        Schema::create('usage_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->decimal('kwh', 14, 4);
            $table->timestamps();
            $table->unique(['building_id', 'recorded_at']);
        });

        Schema::create('utility_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('kwh', 14, 3);
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->unique(['building_id', 'period_start', 'period_end']);
        });

        Schema::create('forecast_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('model', 80)->nullable();
            $table->timestamp('input_started_at')->nullable();
            $table->timestamp('input_ended_at')->nullable();
            $table->decimal('baseline_daily_kwh', 14, 3)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('forecast_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_run_id')->constrained()->cascadeOnDelete();
            $table->date('forecast_date');
            $table->decimal('kwh', 14, 3);
            $table->decimal('peak_kw', 14, 3);
            $table->unique(['forecast_run_id', 'forecast_date']);
        });

        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_run_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('priority', 12)->default('medium');
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('trialing');
            $table->unsignedInteger('monthly_price_cents')->default(9900);
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('demo_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('status', 20);
            $table->string('demo_reference')->unique();
            $table->timestamps();
        });

        Schema::create('contact_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('company')->nullable();
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['contact_leads', 'demo_payments', 'subscriptions', 'recommendations', 'forecast_points', 'forecast_runs', 'utility_bills', 'usage_readings', 'buildings', 'invitations', 'organization_user', 'organizations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
