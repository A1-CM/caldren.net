<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_model_releases', function (Blueprint $table): void {
            $table->id();
            $table->string('version', 80)->unique();
            $table->json('training_organization_ids');
            $table->string('status', 20)->default('active');
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'activated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_model_releases');
    }
};
