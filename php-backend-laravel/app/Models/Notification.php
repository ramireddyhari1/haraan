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
        'all'          => 'Everyone',
        'district'     => 'A district',
        'state'        => 'A state',
        'sport'        => 'A sport',
        'user'         => 'One user',
        'active_today' => 'Active today',
        'active_7d'    => 'Active in the last 7 days',
        'active_30d'   => 'Active in the last 30 days',
        'inactive_14d' => 'Lapsed — inactive 14+ days',
        'inactive_30d' => 'Lapsed — inactive 30+ days',
    ];

    /**
     * Activity segments are time-relative, so they are resolved to a concrete recipient
     * list the moment the message is sent (see snapshotActivityRecipients) rather than
     * matched live per-user like the static segments above.
     */
    public const ACTIVITY_AUDIENCE_TYPES = [
        'active_today', 'active_7d', 'active_30d', 'inactive_14d', 'inactive_30d',
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

        // After the row exists, freeze an activity segment into a recipient list. Guarded
        // by "no recipients yet" so a later edit never re-snapshots against fresh activity.
        static::saved(function (Notification $n): void {
            if ($n->status === 'sent'
                && in_array($n->audience_type, self::ACTIVITY_AUDIENCE_TYPES, true)
                && ! $n->recipients()->exists()) {
                $n->snapshotActivityRecipients();
            }
        });
    }

    public function reads(): HasMany
    {
        return $this->hasMany(NotificationRead::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    /**
     * Resolve this notification's activity segment to the user ids that match right now
     * and freeze them into notification_recipients. Chunked insert so a large lapsed
     * cohort doesn't build one giant array/query.
     */
    public function snapshotActivityRecipients(): void
    {
        $this->activityAudienceQuery()
            ->select('id')
            ->chunkById(500, function ($users): void {
                $rows = $users->map(fn ($u) => [
                    'notification_id' => $this->id,
                    'user_id' => $u->id,
                ])->all();

                if ($rows !== []) {
                    NotificationRecipient::insertOrIgnore($rows);
                }
            });
    }

    /**
     * How many users this notification was aimed at. Activity segments are exact (the
     * frozen recipient snapshot); static segments have no snapshot, so we report the
     * users who match the segment now — a close proxy for the send-time audience.
     */
    public function reach(): int
    {
        if (in_array($this->audience_type, self::ACTIVITY_AUDIENCE_TYPES, true)) {
            return $this->recipients()->count();
        }

        return match ($this->audience_type) {
            'all'      => User::count(),
            'user'     => $this->audience_value !== null ? 1 : 0,
            'district' => User::where('district', $this->audience_value)->count(),
            'state'    => User::where('state', $this->audience_value)->count(),
            'sport'    => User::where('primary_sport', $this->audience_value)->count(),
            default    => 0,
        };
    }

    /** Open rate as a 0–100 percentage (reads ÷ reach); null when there's no audience. */
    public function openRate(): ?float
    {
        $reach = $this->reach();
        if ($reach <= 0) {
            return null;
        }

        $reads = $this->reads_count ?? $this->reads()->count();

        return round(min($reads, $reach) / $reach * 100, 1);
    }

    /** The User query for this notification's activity segment. */
    public function activityAudienceQuery(): Builder
    {
        $q = User::query();
        $now = now();

        return match ($this->audience_type) {
            'active_today' => $q->where('last_seen_at', '>=', $now->copy()->subDay()),
            'active_7d'    => $q->where('last_seen_at', '>=', $now->copy()->subDays(7)),
            'active_30d'   => $q->where('last_seen_at', '>=', $now->copy()->subDays(30)),
            // Lapsed = has used the app before, but not within the window. Never-active
            // users are excluded — there is no installed app to receive the bell.
            'inactive_14d' => $q->whereNotNull('last_seen_at')->where('last_seen_at', '<', $now->copy()->subDays(14)),
            'inactive_30d' => $q->whereNotNull('last_seen_at')->where('last_seen_at', '<', $now->copy()->subDays(30)),
            default        => $q->whereRaw('1 = 0'),
        };
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
                ->orWhere(fn (Builder $s) => $s->where('audience_type', 'user')->where('audience_value', (string) $user->id))
                // Activity segments were frozen to a recipient list at send time.
                ->orWhereHas('recipients', fn (Builder $s) => $s->where('user_id', $user->id));
        });
    }
}
