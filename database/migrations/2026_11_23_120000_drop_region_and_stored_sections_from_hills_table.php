<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One name, one thing (spec §1). hills.region was a copy of the MWIS area name
 * for the 282 Munros, and the stored Murray section and sub-section (smc_section
 * and rhb_section) are worked out from dobih_section for every hill, with
 * identical results, so none of them is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hills', function (Blueprint $table) {
            $table->dropIndex(['region']);
        });

        Schema::table('hills', function (Blueprint $table) {
            $table->dropColumn(['region', 'smc_section', 'smc_section_description', 'rhb_section', 'rhb_section_description']);
        });
    }

    public function down(): void
    {
        Schema::table('hills', function (Blueprint $table) {
            $table->string('region')->nullable()->index()->after('height_m');
            $table->unsignedSmallInteger('smc_section')->nullable();
            $table->string('smc_section_description')->nullable();
            $table->string('rhb_section')->nullable();
            $table->string('rhb_section_description')->nullable();
        });
    }
};
