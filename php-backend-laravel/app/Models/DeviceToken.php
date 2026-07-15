<?php

declare(strict_types=1);

namespace App\Models;

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

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
