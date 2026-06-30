<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crex-style "About" fields collected during ActionBoard profile setup.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('bowling_style');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('birth_place')->nullable()->after('date_of_birth');
            $table->string('height')->nullable()->after('birth_place');
            $table->string('nationality')->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'gender',
                'date_of_birth',
                'birth_place',
                'height',
                'nationality',
            ]);
        });
    }
};
