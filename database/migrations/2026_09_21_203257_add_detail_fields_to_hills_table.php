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
        Schema::table('hills', function (Blueprint $table) {
            $table->string('slug')->unique()->after('name');
            $table->unsignedInteger('dobih_number')->unique()->after('slug');
            $table->string('grid_ref')->after('mwis_forecast_url');
            $table->decimal('latitude', 9, 6)->after('grid_ref');
            $table->decimal('longitude', 9, 6)->after('latitude');
            $table->unsignedSmallInteger('smc_section')->after('longitude');
            $table->string('smc_section_description')->after('smc_section');
            $table->string('rhb_section')->after('smc_section_description');
            $table->string('rhb_section_description')->after('rhb_section');
            $table->string('photo_url')->nullable()->after('rhb_section_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hills', function (Blueprint $table) {
            $table->dropColumn([
                'slug',
                'dobih_number',
                'grid_ref',
                'latitude',
                'longitude',
                'smc_section',
                'smc_section_description',
                'rhb_section',
                'rhb_section_description',
                'photo_url',
            ]);
        });
    }
};
