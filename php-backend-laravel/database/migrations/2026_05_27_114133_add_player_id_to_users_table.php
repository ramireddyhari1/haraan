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
            $table->string('player_id', 6)->unique()->nullable()->after('id');
        });

        // Backfill existing users
        $users = \App\Models\User::whereNull('player_id')->get();
        foreach ($users as $user) {
            do {
                $pid = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            } while (\App\Models\User::where('player_id', $pid)->exists());
            $user->update(['player_id' => $pid]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('player_id');
        });
    }
};
