<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;

/**
 * The words on the website's /support page that the business tunes — edited from
 * /control → Support topics → "Support page text". Stored in {@see AppSetting}
 * under the `support` group; the defaults below are only the fallback.
 */
final class SupportPageCopy
{
    public const GROUP = 'support';

    /** key => [default, max length, admin label, admin help] */
    public const TEXTS = [
        'reply_time' => [
            'The team usually replies within a day.',
            120,
            'Reply-time line',
            'Shown under "Haraan Support" until someone is assigned. Keep it true — people plan around it.',
        ],
        'prompt' => [
            'What do you need help with?',
            80,
            'Topic question',
            'The question above the topic list on a new conversation.',
        ],
        'composer_hint' => [
            'Your booking ID helps us find it',
            90,
            'Message box hint',
            'Placeholder in the message box before the first message.',
        ],
    ];

    public static function storageKey(string $key): string
    {
        return self::GROUP . '.' . $key;
    }

    public static function text(string $key): string
    {
        $value = trim((string) AppSetting::get(self::storageKey($key), ''));

        return $value !== '' ? $value : self::TEXTS[$key][0];
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(self::TEXTS) as $key) {
            $out[$key] = self::text($key);
        }

        return $out;
    }
}
