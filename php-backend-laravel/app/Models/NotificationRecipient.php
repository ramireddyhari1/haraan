<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A frozen recipient of an activity-segmented notification. The presence of a row is
 * the whole signal (join target for the bell query), so there are no timestamps.
 * Written by {@see Notification::snapshotActivityRecipients()}.
 */
class NotificationRecipient extends Model
{
    public $timestamps = false;

    protected $fillable = ['notification_id', 'user_id'];
}
