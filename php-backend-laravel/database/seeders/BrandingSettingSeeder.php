<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

/**
 * Phase 2 — default branding/theme. Idempotent (does not overwrite values an
 * admin has already set). Edit these in /control → Platform → Branding & theme.
 */
class BrandingSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'app_name' => 'Haraan',
            'tagline' => 'Your district. Your game.',
            'primary_color' => '#16a34a',  // green
            'accent_color' => '#2563eb',   // blue
            'support_whatsapp' => '',
        ];

        foreach ($defaults as $key => $value) {
            if (AppSetting::where('key', $key)->doesntExist()) {
                AppSetting::set($key, $value, 'branding');
            }
        }

        $this->command?->info('Seeded branding defaults.');
    }
}
