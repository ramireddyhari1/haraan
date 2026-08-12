<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Split the event poster from its gallery. Historically `images` held the poster
 * at index 0 and every extra photo (the detail-page "Gallery") at 1+. Going
 * forward the poster is a single image in `images` and the gallery lives in its
 * own `gallery` column, managed from a dedicated Gallery step in the partner
 * console. Backfill moves each event's images[1..] into `gallery` and trims
 * `images` to just the poster, so no existing gallery is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('events', 'gallery')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->json('gallery')->nullable()->after('images');
            });
        }

        foreach (DB::table('events')->select('id', 'images')->get() as $row) {
            $images = $this->decode($row->images);
            if (count($images) < 2) {
                continue; // poster-only or empty — nothing to split
            }

            DB::table('events')->where('id', $row->id)->update([
                'images'  => json_encode([$images[0]]),
                'gallery' => json_encode(array_values(array_slice($images, 1))),
            ]);
        }
    }

    public function down(): void
    {
        // Re-fold gallery back into images so a rollback keeps the old shape.
        foreach (DB::table('events')->select('id', 'images', 'gallery')->get() as $row) {
            $gallery = $this->decode($row->gallery);
            if ($gallery === []) {
                continue;
            }
            $images = $this->decode($row->images);
            DB::table('events')->where('id', $row->id)->update([
                'images' => json_encode(array_values(array_merge($images, $gallery))),
            ]);
        }

        if (Schema::hasColumn('events', 'gallery')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->dropColumn('gallery');
            });
        }
    }

    /** @return list<string> */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($v): bool => is_string($v) && trim($v) !== ''));
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded)
            ? array_values(array_filter($decoded, fn ($v): bool => is_string($v) && trim($v) !== ''))
            : [];
    }
};
