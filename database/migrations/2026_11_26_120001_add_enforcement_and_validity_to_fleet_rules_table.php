<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_rules', function (Blueprint $table) {
            // block = reject the purchase; warn = allow it and notify.
            $table->string('enforcement', 10)->default('block')->after('rule_value');
            $table->date('valid_from')->nullable()->after('enforcement');
            $table->date('valid_to')->nullable()->after('valid_from');
        });

        DB::statement("ALTER TABLE fleet_rules ADD CONSTRAINT fleet_rules_enforcement_check CHECK (enforcement IN ('block', 'warn'))");

        // The original CHECK only allowed the four initial types; widen it to
        // include the new limits and the days/hours rule.
        DB::statement('ALTER TABLE fleet_rules DROP CHECK fleet_rules_type_check');
        DB::statement("ALTER TABLE fleet_rules ADD CONSTRAINT fleet_rules_type_check CHECK (rule_type IN ('allowed_products', 'allowed_stations', 'max_liters_per_transaction', 'max_transactions_per_day', 'max_liters_per_day', 'max_amount_per_transaction_usd', 'max_amount_per_day_usd', 'allowed_time_windows'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE fleet_rules DROP CHECK fleet_rules_type_check');
        DB::statement("ALTER TABLE fleet_rules ADD CONSTRAINT fleet_rules_type_check CHECK (rule_type IN ('allowed_products', 'allowed_stations', 'max_liters_per_transaction', 'max_transactions_per_day'))");
        DB::statement('ALTER TABLE fleet_rules DROP CHECK fleet_rules_enforcement_check');

        Schema::table('fleet_rules', function (Blueprint $table) {
            $table->dropColumn(['enforcement', 'valid_from', 'valid_to']);
        });
    }
};
