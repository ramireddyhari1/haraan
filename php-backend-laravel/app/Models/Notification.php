<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BroadcastsContentChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A broadcast notification composed by the admin/Haraan team and shown in the
 * app's bell inbox. Saving one broadcasts a `content.updated {domain:notifications}`
 * signal (via {@see BroadcastsContentChanges}) so open apps refetch the bell live.
 *
 * @property int         $id
 * @property string      $title
 * @property string      $body
 * @property string|null $image_url
 * @property string|null $deep_link
 * @property string      $audience_type
 * @property string|null $audience_value
 * @property string      $status
 * @property \Illuminate\Support\Carbon|null $sent_at
 */
class Notification extends Model
{
    use BroadcastsContentChanges;

    /** Client domain to refetch on change — the app's bell inbox. */
    protected string $contentDomain = 'notifications';

    /** How an audience is described. Kept in sync with the Filament composer. */
    public const AUDIENCE_TYPES = [
        'all'      => 'Everyone',
        'district' => 'A district',
        'state'    => 'A state',
        'sport'    => 'A sport',
        'user'     => 'One user',
    ];

    protected $fillable = [
        'title',
        'body',
        'image_url',
        'deep_link',
        'audience_type',
        'audience_value',
        'status',
        'scheduled_at',
        'sent_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at'      => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Notification $n): void {
            // Stamp the delivery time the first moment it flips to "sent".
            if ($n->status === 'sent' && $n->sent_at === null) {
                $n->sent_at = now();
            }
            // Attribute to the composing admin (API never creates these).
            if ($n->created_by === null && auth()->check()) {
                $n->created_by = auth()->id();
            }
        });
    }

    public function reads(): HasMany
    {
        return $this->hasMany(NotificationRead::class);
    }

    /** Only delivered notifications, newest first. */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', 'sent')->orderByDesc('sent_at')->orderByDesc('id');
    }

    /**
     * Narrow to the notifications a given user should see: global ones plus any
     * whose segment matches the user's district / state / primary sport / id.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user): void {
            $q->where('audience_type', 'all')
                ->orWhere(fn (Builder $s) => $s->where('audience_type', 'district')->where('audience_value', $user->district))
                ->orWhere(fn (Builder $s) => $s->where('audience_type', 'state')->where('audience_value', $user->state))
                ->orWhere(fn (Builder $s) => $s->where('audience_type', 'sport')->where('audience_value', $user->primary_sport))
                ->orWhere(fn (Builder $s) => $s->where('audience_type', 'user')->where('audience_value', (string) $user->id));
        });
    }
}
