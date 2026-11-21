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
        // A name only: someone on a walk with no account, who has no history.
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['group_id', 'name']);
        });

        Schema::create('walks', function (Blueprint $table) {
            $table->id();
            // Null for a solo walk.
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('status', 16)->default('completed')->index();
            // Kept if the account is deleted, shown as a "former member".
            $table->foreignId('organiser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('walk_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('walk_id')->constrained()->cascadeOnDelete();
            // Null once plans can be postponed; always set for a logged walk.
            $table->date('date')->nullable()->index();
            $table->unsignedSmallInteger('day_number');
            $table->string('direction', 16)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->timestamps();

            $table->unique(['walk_id', 'day_number']);
        });

        Schema::create('walk_day_hill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('walk_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hill_id')->constrained()->cascadeOnDelete();
            // The order the hills were climbed in.
            $table->unsignedSmallInteger('position');

            $table->unique(['walk_day_id', 'hill_id']);
        });

        Schema::create('walk_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('walk_id')->constrained()->cascadeOnDelete();
            // One of these is set. Both null means a former member.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rsvp', 8)->default('going');
            $table->boolean('attended')->default(false);
            $table->timestamps();
        });

        Schema::create('ascents', function (Blueprint $table) {
            $table->id();
            // Ascents belong to the person: deleting the account deletes them.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hill_id')->constrained()->cascadeOnDelete();
            // Every ascent belongs to a walk day, even history.
            $table->foreignId('walk_day_id')->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'hill_id', 'walk_day_id']);
            $table->index(['user_id', 'hill_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ascents');
        Schema::dropIfExists('walk_attendees');
        Schema::dropIfExists('walk_day_hill');
        Schema::dropIfExists('walk_days');
        Schema::dropIfExists('walks');
        Schema::dropIfExists('guests');
    }
};
