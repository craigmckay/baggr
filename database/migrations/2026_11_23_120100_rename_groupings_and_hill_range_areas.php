<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One name, one thing (spec §1). The groupings SMC and RHB become Murray section
 * and Murray sub-section, and DoBIH's D-type areas ("regions") become hill ranges.
 */
return new class extends Migration
{
    private const GROUPINGS = ['smc' => 'murray', 'rhb' => 'murray-sub'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('hill_grouping', 16)->default('mwis')->change();
        });

        foreach (self::GROUPINGS as $old => $new) {
            DB::table('users')->where('hill_grouping', $old)->update(['hill_grouping' => $new]);
        }

        DB::table('areas')->where('type', 'region')->update(['type' => 'hill_range']);
    }

    public function down(): void
    {
        foreach (self::GROUPINGS as $old => $new) {
            DB::table('users')->where('hill_grouping', $new)->update(['hill_grouping' => $old]);
        }

        DB::table('areas')->where('type', 'hill_range')->update(['type' => 'region']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('hill_grouping', 8)->default('mwis')->change();
        });
    }
};
