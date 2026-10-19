<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('plan_key', 20)->nullable()->after('organization_id');
        });

        DB::table('subscriptions')->where('status', 'active')->where('monthly_price_cents', 9900)->update(['plan_key' => 'portfolio']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('plan_key');
        });
    }
};
