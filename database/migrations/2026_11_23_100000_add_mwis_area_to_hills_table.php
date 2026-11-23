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
            // The code of the MWIS forecast area the hill belongs to (NW, WH, ...), if any.
            $table->string('mwis_area', 4)->nullable()->index()->after('mwis_forecast_url');
        });

        // Fill it in from the DoBIH areas, so the live site needs no second step.
        (new MwisAreaMapper)->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hills', function (Blueprint $table) {
            $table->dropColumn('mwis_area');
        });
    }
};
