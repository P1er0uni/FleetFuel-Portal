<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->string('status', 20)->default('active');
            // Null means unlimited; the aggregated monthly quota of the fleet.
            $table->decimal('monthly_quota_liters', 12, 3)->nullable();
            $table->decimal('monthly_quota_amount', 18, 2)->nullable();
            $table->timestamps();

            // No two fleets with the same name inside one company.
            $table->unique(['company_id', 'name']);
        });

        DB::statement("ALTER TABLE fleets ADD CONSTRAINT fleets_status_check CHECK (status IN ('active', 'inactive'))");
        DB::statement('ALTER TABLE fleets ADD CONSTRAINT fleets_quota_liters_nonnegative_check CHECK (monthly_quota_liters >= 0)');
        DB::statement('ALTER TABLE fleets ADD CONSTRAINT fleets_quota_amount_nonnegative_check CHECK (monthly_quota_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('fleets');
    }
};
