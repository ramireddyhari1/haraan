<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A support conversation between one app user and the Haraan support team.
 *
 * @property int         $id
 * @property int         $user_id
 * @property int|null    $category_id
 * @property string|null $subject
 * @property string      $status
 * @property int|null    $assigned_to
 * @property \Carbon\Carbon|null $last_message_at
 * @property int         $user_unread_count
 * @property int         $admin_unread_count
 */
class SupportThread extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'subject',
        'status',
        'assigned_to',
        'last_message_at',
        'user_unread_count',
        'admin_unread_count',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at'    => 'datetime',
            'user_unread_count'  => 'integer',
            'admin_unread_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The issue topic the user picked when opening the chat, if any. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SupportCategory::class, 'category_id');
    }

    /** The admin/worker currently handling this thread, if any. */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'thread_id')->orderBy('id');
    }
}
