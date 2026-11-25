<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vehicles, drivers and fuel cards may belong to 0 or 1 fleet of their
     * own company. Nullable: a member without a fleet behaves exactly as
     * before. Deleting the fleet sets the column to NULL (SET NULL), so the
     * member keeps working, only ungrouped.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('fleet_id')->nullable()->after('company_id')->constrained('fleets')->nullOnDelete();
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->foreignId('fleet_id')->nullable()->after('company_id')->constrained('fleets')->nullOnDelete();
        });

        Schema::table('fuel_cards', function (Blueprint $table) {
            $table->foreignId('fleet_id')->nullable()->after('company_id')->constrained('fleets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fuel_cards', fn (Blueprint $table) => $table->dropConstrainedForeignId('fleet_id'));
        Schema::table('drivers', fn (Blueprint $table) => $table->dropConstrainedForeignId('fleet_id'));
        Schema::table('vehicles', fn (Blueprint $table) => $table->dropConstrainedForeignId('fleet_id'));
    }
};
