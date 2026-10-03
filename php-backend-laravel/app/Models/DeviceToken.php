<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\PlatformRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A device's push (FCM) registration token. Written now via /api/devices/register
 * so Phase 2 can deliver background push to a segment's devices.
 *
 * @property int    $user_id
 * @property string $token
 * @property string $platform
 */
class DeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'last_seen_at',
    ];

    /** A browser registration (the partner console installed as a web app). */
    public const PLATFORM_WEB = 'web';

    /**
     * Tokens a push may go to right now. Browser tokens drop out while an admin has web
     * alerts switched off (/control → Platform rules → Partner web app), so every sender
     * honours that one switch without checking it itself.
     *
     * @return Builder<self>
     */
    public static function pushable(): Builder
    {
        $query = self::query();

        if (! PlatformRules::bool('partner_web_app.push_enabled')) {
            $query->where('platform', '!=', self::PLATFORM_WEB);
        }

        return $query;
    }

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
