<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Coupon extends Model
{
    use HasFactory;

    protected $fillable = ['event_id','code','discount','max_uses','uses','active'];

    protected $casts = [
        'event_id' => 'integer',
        'discount' => 'float',
        'max_uses' => 'integer',
        'uses' => 'integer',
        'active' => 'boolean',
    ];

    /** The event this coupon is scoped to; null = global (works on any event). */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * True when this coupon may be used for the given event. A global coupon
     * (`event_id` null) applies everywhere; a scoped coupon only to its event.
     */
    public function appliesToEvent(?int $eventId): bool
    {
        return $this->event_id === null || (int) $this->event_id === (int) $eventId;
    }

    /** Find a coupon by code (case-insensitive), or null. */
    public static function findByCode(?string $code): ?self
    {
        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        return self::query()->whereRaw('lower(code) = ?', [strtolower($code)])->first();
    }

    /** True when this coupon is active and hasn't exhausted its usage cap. */
    public function isRedeemable(): bool
    {
        if (! $this->active) {
            return false;
        }

        return $this->max_uses === null || $this->uses < $this->max_uses;
    }
}
