<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_id')->constrained('fleets')->cascadeOnDelete();
            $table->string('rule_type', 40);
            // JSON array of IDs for list rules, a decimal string for numeric ones.
            $table->json('rule_value');
            $table->boolean('active')->default(true);
            $table->timestamps();

            // One rule per type per fleet: the POS check reads one row per type.
            $table->unique(['fleet_id', 'rule_type']);
        });

        DB::statement("ALTER TABLE fleet_rules ADD CONSTRAINT fleet_rules_type_check CHECK (rule_type IN ('allowed_products', 'allowed_stations', 'max_liters_per_transaction', 'max_transactions_per_day'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_rules');
    }
};
