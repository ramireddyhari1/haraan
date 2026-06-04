<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('USER')->after('avatar');
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('ACTIVE')->after('role');
            }
            if (!Schema::hasColumn('users', 'partner_type')) {
                $table->string('partner_type')->nullable()->after('status');
            }
            if (!Schema::hasColumn('users', 'event_host_id')) {
                $table->string('event_host_id')->nullable()->unique()->after('partner_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $drops = ['event_host_id', 'partner_type', 'status', 'role', 'avatar', 'phone'];
            foreach ($drops as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
