<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue categories for the in-app support chat. The app shows these as a topic
 * picker before the conversation starts, so the team knows what a thread is
 * about without asking. Rows are admin-managed from the Filament control panel
 * — adding a category must never require an app release, which is why the icon
 * is stored as an emoji the app renders verbatim rather than a drawable key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            // Rendered as-is by the app; an emoji keeps new categories release-free.
            $table->string('icon', 16)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::table('support_threads', function (Blueprint $table): void {
            // Nullable: threads opened before this existed (and "Something else")
            // carry no category, and admins may clear a wrong one.
            $table->foreignId('category_id')->nullable()->after('user_id')
                ->constrained('support_categories')->nullOnDelete();
        });

        DB::table('support_categories')->insert($this->defaults());
    }

    public function down(): void
    {
        Schema::table('support_threads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('support_categories');
    }

    /**
     * Starting set, mirroring the app's two lanes (Tickets / Play) plus the
     * account and partner surfaces. Admins can rename, reorder or retire any of
     * these; nothing in the code refers to them by id.
     *
     * @return list<array<string, mixed>>
     */
    private function defaults(): array
    {
        $now = now();

        $rows = [
            ['🎟️', 'Tickets & bookings'],
            ['💳', 'Payments & refunds'],
            ['🏏', 'Matches & scoring'],
            ['📍', 'Venue booking'],
            ['👤', 'Account & profile'],
            ['🤝', 'Partner or host help'],
        ];

        return array_map(static fn (array $row, int $i): array => [
            'label'      => $row[1],
            'icon'       => $row[0],
            'sort_order' => ($i + 1) * 10,
            'is_active'  => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows, array_keys($rows));
    }
};
