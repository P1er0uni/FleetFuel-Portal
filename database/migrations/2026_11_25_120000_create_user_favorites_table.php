<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Morph-map alias of the entity: fleet, vehicle or station (D7).
            $table->string('favoritable_type', 50);
            $table->unsignedBigInteger('favoritable_id');
            $table->string('label', 100)->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'favoritable_type', 'favoritable_id']);
            $table->index(['user_id', 'position']);
        });

        DB::statement("ALTER TABLE user_favorites ADD CONSTRAINT user_favorites_type_check CHECK (favoritable_type IN ('fleet', 'vehicle', 'station'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('user_favorites');
    }
};
