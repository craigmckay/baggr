<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // One row for each area and day, kept after the day has passed.
        Schema::create('mwis_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('area', 4);
            $table->date('date');
            $table->timestamp('issued_at')->nullable();
            $table->text('headline')->nullable();
            $table->text('wind')->nullable();
            $table->unsignedSmallInteger('wind_mph')->nullable();
            $table->text('rain')->nullable();
            $table->text('cloud_free')->nullable();
            $table->unsignedTinyInteger('cloud_free_percent')->nullable();
            $table->text('freezing_level')->nullable();
            $table->timestamps();

            $table->unique(['area', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mwis_forecasts');
    }
};
