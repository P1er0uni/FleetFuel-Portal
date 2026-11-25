<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('notification_rule_id')->constrained('notification_rules')->cascadeOnDelete();
            // The manager who receives it in the in-app inbox; null for the
            // email and log channels, which do not fill the inbox.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50);
            $table->string('title', 255);
            $table->text('message');
            $table->json('payload')->nullable();
            // Stable hash of the event's entity, for the 1-hour deduplication (D11).
            $table->string('entity_hash', 64);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'read_at']);
            $table->index(['user_id', 'read_at']);
            // The deduplication lookup: latest of (rule, event, entity) within an hour.
            $table->index(['notification_rule_id', 'event_type', 'entity_hash']);
        });

        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_event_type_check CHECK (event_type IN ('quota_exceeded', 'quota_warning', 'card_blocked', 'anomaly_detected', 'delivery_completed', 'delivery_cancelled', 'fleet_rule_violated'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
