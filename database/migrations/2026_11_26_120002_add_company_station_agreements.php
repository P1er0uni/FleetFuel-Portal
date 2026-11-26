<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // When false (default), the company may use any active station.
            // When true, only the stations in company_stations.
            $table->boolean('restrict_stations')->default(false)->after('status');
        });

        Schema::create('company_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('station_id')->constrained('stations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_stations');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('restrict_stations');
        });
    }
};
