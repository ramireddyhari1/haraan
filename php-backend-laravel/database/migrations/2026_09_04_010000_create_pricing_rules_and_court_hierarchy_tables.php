<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Extend venue_courts for Court Split / Merge Topology
        Schema::table('venue_courts', function (Blueprint $table): void {
            $table->foreignId('parent_court_id')->nullable()->after('venue_id')->constrained('venue_courts')->nullOnDelete();
            $table->boolean('is_composite')->default(false)->after('parent_court_id');
            $table->string('split_type', 20)->default('none')->after('is_composite'); // none, half, third, quarter, custom
            $table->string('partition_label', 50)->nullable()->after('split_type');
            $table->boolean('allow_simultaneous_booking')->default(false)->after('partition_label');

            $table->index(['venue_id', 'parent_court_id']);
        });

        // 2. Create pricing_rules Table
        Schema::create('pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_court_id')->nullable()->constrained('venue_courts')->cascadeOnDelete();

            $table->string('name', 120);
            $table->string('rule_type', 30)->default('time_of_day'); // time_of_day, day_of_week, seasonal_date_range, occupancy_surge, last_minute
            $table->json('weekdays')->nullable(); // ["monday","tuesday",...] or ["Mon","Tue",...]
            $table->string('start_time', 10);     // 18:00
            $table->string('end_time', 10);       // 22:00
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();

            $table->string('pricing_mode', 20)->default('absolute'); // absolute, delta, percentage
            $table->decimal('amount', 10, 2);
            $table->decimal('min_price', 10, 2)->nullable();
            $table->decimal('max_price', 10, 2)->nullable();
            $table->integer('priority')->default(10);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['venue_id', 'venue_court_id', 'is_active']);
            $table->index(['venue_id', 'priority', 'is_active']);
        });

        // 3. Create pricing_rule_logs Table (Audit Trail)
        Schema::create('pricing_rule_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pricing_rule_id')->nullable()->constrained('pricing_rules')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('action', 50); // created, updated, toggled, deleted, court_split, court_merged
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['venue_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rule_logs');
        Schema::dropIfExists('pricing_rules');

        Schema::table('venue_courts', function (Blueprint $table): void {
            $table->dropForeign(['parent_court_id']);
            $table->dropIndex(['venue_id', 'parent_court_id']);
            $table->dropColumn([
                'parent_court_id',
                'is_composite',
                'split_type',
                'partition_label',
                'allow_simultaneous_booking',
            ]);
        });
    }
};
