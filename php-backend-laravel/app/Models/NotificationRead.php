<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Records that a user has opened a given notification — drives the unread badge
 * and "mark read". One row per (notification, user).
 *
 * @property int $notification_id
 * @property int $user_id
 */
class NotificationRead extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'notification_id',
        'user_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
