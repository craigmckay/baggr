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
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('colour', 7)->default('#1F4D3A');
            // The reusable link people use to ask to join. Null when there is none.
            $table->string('join_token', 64)->nullable()->unique();
            $table->timestamp('join_token_expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // admin, member, follower or visitor (see App\Enums\GroupRole).
            $table->string('role', 16)->index();
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
        });

        Schema::create('group_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('email')->index();
            // admin, member or follower. A visitor can't be invited.
            $table->string('role', 16);
            $table->string('token', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_invites');
        Schema::dropIfExists('group_user');
        Schema::dropIfExists('groups');
    }
};
