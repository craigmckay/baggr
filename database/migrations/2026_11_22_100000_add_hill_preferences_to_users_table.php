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
        Schema::table('users', function (Blueprint $table) {
            // The colour of hills done solo, and the way regions are grouped on My hills.
            $table->string('colour', 7)->default('#C9B5DD')->after('email');
            $table->string('hill_grouping', 8)->default('mwis')->after('colour');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['colour', 'hill_grouping']);
        });
    }
};
