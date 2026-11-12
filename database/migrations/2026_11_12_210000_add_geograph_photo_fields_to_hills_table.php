<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hills', function (Blueprint $table) {
            $table->string('photo_page_url')->nullable()->after('photo_url');
            $table->string('photo_title')->nullable()->after('photo_page_url');
            $table->string('photo_author')->nullable()->after('photo_title');
            $table->unsignedBigInteger('photo_geograph_id')->nullable()->after('photo_author');
        });

        // Photos now come from Geograph (CC BY-SA 2.0). The previously scraped
        // Walkhighlands image URLs have no licence or credit, so drop them.
        DB::table('hills')
            ->where('photo_url', 'like', '%walkhighlands.co.uk%')
            ->update(['photo_url' => null]);
    }

    /**
     * Reverse the migrations.
     *
     * The Walkhighlands photo URLs are not restored.
     */
    public function down(): void
    {
        Schema::table('hills', function (Blueprint $table) {
            $table->dropColumn(['photo_page_url', 'photo_title', 'photo_author', 'photo_geograph_id']);
        });
    }
};
