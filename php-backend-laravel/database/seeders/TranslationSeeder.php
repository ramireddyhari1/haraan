<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Translation;
use Illuminate\Database\Seeder;

/**
 * Phase 2 — starter localization strings. Idempotent (upsert by key+locale).
 * English is the source set; Telugu is seeded as a sample translation. Other
 * locales fall back to English until filled in /control → Localization.
 */
class TranslationSeeder extends Seeder
{
    public function run(): void
    {
        $strings = [
            // group, key, [locale => value]
            ['common', 'common.start_scoring', ['en' => 'Start scoring', 'te' => 'స్కోరింగ్ ప్రారంభించండి']],
            ['common', 'common.share', ['en' => 'Share', 'te' => 'షేర్ చేయండి']],
            ['common', 'common.cancel', ['en' => 'Cancel', 'te' => 'రద్దు చేయండి']],
            ['match_detail', 'match_detail.live', ['en' => 'LIVE', 'te' => 'ప్రత్యక్షం']],
            ['match_detail', 'match_detail.scorecard', ['en' => 'Scorecard', 'te' => 'స్కోర్‌కార్డ్']],
            ['match_detail', 'match_detail.commentary', ['en' => 'Commentary']], // en only → others fall back
        ];

        $count = 0;
        foreach ($strings as [$group, $key, $byLocale]) {
            foreach ($byLocale as $locale => $value) {
                Translation::updateOrCreate(
                    ['key' => $key, 'locale' => $locale],
                    ['group' => $group, 'value' => $value],
                );
                $count++;
            }
        }

        $this->command?->info("Seeded {$count} translation row(s).");
    }
}
