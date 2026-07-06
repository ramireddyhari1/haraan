<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single message in a {@see SupportThread}. `sender_type` is 'user' (from the
 * app) or 'admin' (a reply from the control panel).
 *
 * @property int         $id
 * @property int         $thread_id
 * @property string      $sender_type
 * @property int|null    $sender_id
 * @property string      $body
 */
class SupportMessage extends Model
{
    protected $fillable = [
        'thread_id',
        'sender_type',
        'sender_id',
        'body',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(SupportThread::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function isFromAdmin(): bool
    {
        return $this->sender_type === 'admin';
    }
}
