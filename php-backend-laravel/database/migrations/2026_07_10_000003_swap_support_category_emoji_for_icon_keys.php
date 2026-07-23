<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Emoji icons were release-free but read cheap in the app: the system font
 * renders them at inconsistent weight, size and colour, so six topics looked
 * like six unrelated stickers. Swap to an `icon_key` the app maps onto one
 * Material vector family, and add the `subtitle` the new row layout leans on to
 * let users self-select without guessing.
 *
 * Admins keep release-free *topics*; they give up release-free *icons*. An
 * unknown key degrades to a chat bubble in the app rather than a blank square,
 * so an older build never breaks on a newly-added topic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_categories', function (Blueprint $table): void {
            $table->string('icon_key', 32)->default('chat')->after('label');
            $table->string('subtitle')->nullable()->after('icon_key');
        });

        foreach ($this->seedMapping() as $label => [$key, $subtitle]) {
            DB::table('support_categories')->where('label', $label)->update([
                'icon_key' => $key,
                'subtitle' => $subtitle,
            ]);
        }

        Schema::table('support_categories', function (Blueprint $table): void {
            $table->dropColumn('icon');
        });
    }

    public function down(): void
    {
        Schema::table('support_categories', function (Blueprint $table): void {
            $table->string('icon', 16)->nullable()->after('label');
        });

        Schema::table('support_categories', function (Blueprint $table): void {
            $table->dropColumn(['icon_key', 'subtitle']);
        });
    }

    /**
     * Backfill for the six seeded topics, matched on label so a renamed row is
     * simply skipped (it keeps the 'chat' default rather than getting a wrong icon).
     *
     * @return array<string, array{string, string}>
     */
    private function seedMapping(): array
    {
        return [
            'Tickets & bookings'   => ['ticket', "Missing ticket, QR won't scan"],
            'Payments & refunds'   => ['card', 'Failed payment, refund status'],
            'Matches & scoring'    => ['cricket', 'Wrong score, XP not credited'],
            'Venue booking'        => ['venue', 'Slot changes, turf access'],
            'Account & profile'    => ['account', 'Login, name, phone number'],
            'Partner or host help' => ['partner', 'Payouts, listings, check-in'],
        ];
    }
};
