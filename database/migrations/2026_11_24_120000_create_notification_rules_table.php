<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            // A rule may target one fleet; null means the whole company.
            $table->foreignId('fleet_id')->nullable()->constrained('fleets')->nullOnDelete();
            $table->string('event_type', 50);
            $table->string('channel', 20);
            // Threshold values only apply to quota_warning (percentage of quota).
            $table->decimal('threshold_value', 12, 3)->nullable();
            $table->string('threshold_operator', 3)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'active']);
        });

        DB::statement("ALTER TABLE notification_rules ADD CONSTRAINT notification_rules_event_type_check CHECK (event_type IN ('quota_exceeded', 'quota_warning', 'card_blocked', 'anomaly_detected', 'delivery_completed', 'delivery_cancelled', 'fleet_rule_violated'))");
        DB::statement("ALTER TABLE notification_rules ADD CONSTRAINT notification_rules_channel_check CHECK (channel IN ('in_app', 'email', 'log'))");
        DB::statement("ALTER TABLE notification_rules ADD CONSTRAINT notification_rules_operator_check CHECK (threshold_operator IS NULL OR threshold_operator IN ('gte', 'lte', 'eq'))");
        DB::statement('ALTER TABLE notification_rules ADD CONSTRAINT notification_rules_threshold_nonnegative_check CHECK (threshold_value IS NULL OR threshold_value >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_rules');
    }
};
