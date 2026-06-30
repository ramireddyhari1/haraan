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
        Schema::create('match_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('match_id');
            $table->integer('innings')->default(1);
            $table->integer('over_number')->default(0);
            $table->integer('ball_number')->default(0);
            $table->string('action_type');
            $table->json('payload')->nullable();
            $table->integer('version')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            // Foreign keys
            $table->foreign('match_id')->references('id')->on('live_matches')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_actions');
    }
};
