<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->timestamp('model_training_opt_in_at')->nullable();
            $table->string('model_training_consent_version', 32)->nullable();
            $table->foreignId('model_training_consented_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('model_training_consent_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16);
            $table->string('consent_version', 32);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_training_consent_events');
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('model_training_consented_by');
            $table->dropColumn(['model_training_opt_in_at', 'model_training_consent_version']);
        });
    }
};
