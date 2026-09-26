<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\PlatformRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One customer's place in the WhatsApp booking bot.
 *
 * @property int $id
 * @property string $phone
 * @property string $state
 * @property array|null $data
 * @property int|null $venue_id
 * @property int|null $booking_id
 * @property \Carbon\Carbon|null $last_activity_at
 * @property \Carbon\Carbon|null $paused_until
 */
final class WhatsAppBotSession extends Model
{
    protected $table = 'whatsapp_bot_sessions';

    protected $fillable = ['phone', 'state', 'data', 'venue_id', 'booking_id', 'last_activity_at', 'paused_until'];

    protected function casts(): array
    {
        return [
            'data'             => 'array',
            'last_activity_at' => 'datetime',
            'paused_until'     => 'datetime',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public static function for(string $phone): self
    {
        return self::query()->firstOrCreate(['phone' => $phone], ['state' => 'start', 'data' => []]);
    }

    public function isPaused(): bool
    {
        return $this->paused_until !== null && $this->paused_until->isFuture();
    }

    /** Idle long enough that picking up where they left off would confuse them. */
    public function isStale(): bool
    {
        return $this->last_activity_at === null
            || $this->last_activity_at->lt(now()->subMinutes(PlatformRules::int('whatsapp_bot.session_minutes')));
    }

    /** Staff took over this customer in the desk. */
    public static function pauseFor(string $phone, int $hours): void
    {
        if ($hours <= 0) {
            return;
        }

        self::query()->updateOrCreate(['phone' => $phone], ['paused_until' => now()->addHours($hours)]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return ($this->data ?? [])[$key] ?? $default;
    }

    /** @param array<string, mixed> $values */
    public function put(array $values): void
    {
        $this->data = array_merge($this->data ?? [], $values);
    }
}
