<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make room for every hill in DoBIH, not just the 282 Munros: hill names
     * repeat, and a lot of what the Munros carry (a Walkhighlands page, an
     * MWIS area, SMC and RHB sections) doesn't exist for other hills.
     */
    public function up(): void
    {
        Schema::table('hills', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->index('name');

            $table->string('region')->nullable()->change();
            $table->string('walkhighlands_url')->nullable()->change();
            $table->string('mwis_forecast_url')->nullable()->change();
            $table->string('grid_ref')->nullable()->change();
            $table->decimal('latitude', 9, 6)->nullable()->change();
            $table->decimal('longitude', 9, 6)->nullable()->change();
            $table->unsignedSmallInteger('smc_section')->nullable()->change();
            $table->string('smc_section_description')->nullable()->change();
            $table->string('rhb_section')->nullable()->change();
            $table->string('rhb_section_description')->nullable()->change();

            $table->string('country', 4)->nullable()->index();
            $table->decimal('metres', 7, 2)->nullable();
            $table->unsignedInteger('feet')->nullable();
            $table->decimal('drop_m', 7, 1)->nullable();
            $table->string('col_grid_ref', 20)->nullable();
            $table->decimal('col_height_m', 7, 1)->nullable();
            $table->string('grid_ref_10', 20)->nullable();
            $table->unsignedInteger('easting')->nullable();
            $table->unsignedInteger('northing')->nullable();
            $table->string('dobih_section', 8)->nullable();
            $table->string('classification')->nullable();
            $table->unsignedInteger('parent_marilyn_number')->nullable();
            $table->unsignedInteger('parent_smc_number')->nullable();
            $table->string('feature')->nullable();
            $table->string('observations', 500)->nullable();
            $table->string('survey')->nullable();
            $table->string('comments', 500)->nullable();
            $table->date('revised_on')->nullable();
            $table->boolean('tump_only')->default(false);
            $table->unsignedInteger('mv_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hills', function (Blueprint $table) {
            $table->dropIndex(['country']);
            $table->dropIndex(['name']);

            $table->dropColumn([
                'country', 'metres', 'feet', 'drop_m', 'col_grid_ref', 'col_height_m', 'grid_ref_10',
                'easting', 'northing', 'dobih_section', 'classification', 'parent_marilyn_number',
                'parent_smc_number', 'feature', 'observations', 'survey', 'comments', 'revised_on',
                'tump_only', 'mv_number',
            ]);
        });
    }
};
