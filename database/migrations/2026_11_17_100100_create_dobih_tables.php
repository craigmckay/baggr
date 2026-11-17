<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hill_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hill_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->index('name');
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('short_name');
            $table->string('name');
            $table->string('type', 24)->index();
            $table->string('country', 4)->nullable();
            // Filled in by the area-to-MWIS mapping, which isn't part of the import.
            $table->string('mwis_area')->nullable();
        });

        Schema::create('area_hill', function (Blueprint $table) {
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hill_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_alt')->default(false);
            $table->boolean('is_county_top')->default(false);

            $table->primary(['area_id', 'hill_id']);
            $table->index('hill_id');
        });

        Schema::create('hill_classes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->boolean('show_in_list')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        Schema::create('class_hill', function (Blueprint $table) {
            $table->foreignId('hill_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hill_id')->constrained()->cascadeOnDelete();

            $table->primary(['hill_class_id', 'hill_id']);
            $table->index('hill_id');
        });

        Schema::create('os_maps', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);
            $table->string('ref', 10);
            $table->string('name');

            $table->unique(['type', 'ref']);
        });

        Schema::create('hill_map', function (Blueprint $table) {
            $table->foreignId('hill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('os_map_id')->constrained()->cascadeOnDelete();

            $table->primary(['hill_id', 'os_map_id']);
            $table->index('os_map_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hill_map');
        Schema::dropIfExists('os_maps');
        Schema::dropIfExists('class_hill');
        Schema::dropIfExists('hill_classes');
        Schema::dropIfExists('area_hill');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('hill_names');
    }
};
