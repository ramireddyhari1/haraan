<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One session ("time slot") of an event — a date/time the event runs, with its own
 * capacity and, when the event is in "Customize per slot" mode, its own ticket tiers.
 *
 * An event always has at least one slot (backfilled from its date/time); a single
 * slot behaves exactly like the old single-session event, two or more turn on the
 * multi-session booking experience.
 *
 * @property int         $id
 * @property int         $event_id
 * @property string|null $label
 * @property \Carbon\Carbon|null $starts_at
 * @property \Carbon\Carbon|null $ends_at
 * @property int|null    $capacity  Null = unlimited (bounded by the event's slots).
 * @property int         $sold
 * @property int         $sort
 *
 * @property-read Event $event
 * @property-read \Illuminate\Database\Eloquent\Collection<TicketType> $ticketTypes
 */
final class EventSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'label',
        'starts_at',
        'ends_at',
        'capacity',
        'sold',
        'sort',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
            'capacity'  => 'integer',
            'sold'      => 'integer',
            'sort'      => 'integer',
        ];
    }

    /** Remaining seats for this session, or null when capacity is unlimited. */
    public function remaining(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        return max($this->capacity - $this->sold, 0);
    }

    /** True when this session has a capacity and it's fully sold. */
    public function soldOut(): bool
    {
        return $this->remaining() === 0;
    }

    /**
     * A display label for the session — the host's own label when set, otherwise
     * one derived from the start time (e.g. "12 Jul · 7:00 PM"). Falls back to a
     * plain "Session" when there's nothing to derive from.
     */
    public function displayLabel(): string
    {
        $label = trim((string) $this->label);
        if ($label !== '') {
            return $label;
        }

        if ($this->starts_at !== null) {
            return $this->starts_at->format('d M · g:i A');
        }

        return 'Session';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** Ticket tiers scoped to this session (ordered for display). */
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class)->orderBy('sort')->orderBy('id');
    }
}
