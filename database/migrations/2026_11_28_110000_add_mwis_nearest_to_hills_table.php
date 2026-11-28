<?php

use App\Support\MwisAreaMapper;
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
        Schema::table('hills', function (Blueprint $table) {
            // The hill's MWIS area is only the nearest: MWIS doesn't say it covers the hill.
            $table->boolean('mwis_nearest')->default(false)->after('mwis_area');
        });

        // Fill it in from the DoBIH areas, so the live site needs no second step.
        (new MwisAreaMapper)->markNearestAreas();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hills', function (Blueprint $table) {
            $table->dropColumn('mwis_nearest');
        });
    }
};
