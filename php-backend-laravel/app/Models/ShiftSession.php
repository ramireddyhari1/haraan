<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One person's stint on the desk at one venue, and the cash it should account for.
 *
 * Money is attributed by FK ({@see BookingPayment::$shift_session_id}) rather than
 * by time window, so a backdated payment or two overlapping shifts can never make
 * the same rupee count twice.
 *
 * @property float      $opening_float
 * @property float|null $counted_cash
 * @property float|null $variance  counted − expected; negative is short
 */
class ShiftSession extends Model
{
    /** @use HasFactory<\Database\Factories\ShiftSessionFactory> */
    use HasFactory;

    /** Cash is the only method that lives in a drawer and can therefore go missing. */
    public const DRAWER_METHODS = ['cash'];

    /** Taken over the counter but not in the drawer — reconciled against statements. */
    public const DIGITAL_METHODS = ['upi', 'card'];

    protected $fillable = [
        'venue_id',
        'user_id',
        'opened_by',
        'opened_at',
        'opening_float',
        'closed_at',
        'closed_by',
        'counted_cash',
        'variance',
        'note',
    ];

    protected $attributes = [
        'opening_float' => 0,
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'variance' => 'decimal:2',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** The staff member who was on duty. */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class, 'shift_session_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * Net cash taken during this shift — collections minus any cash refunds paid
     * back out of the same drawer. Refunds are negative rows, so one SUM covers it.
     */
    public function cashMovement(): float
    {
        return (float) $this->payments()
            ->whereIn('method', self::DRAWER_METHODS)
            ->sum('amount');
    }

    /** What should physically be in the drawer right now. */
    public function expectedCash(): float
    {
        return round((float) $this->opening_float + $this->cashMovement(), 2);
    }

    /** UPI + card taken at the counter — reconciled against statements, not counted. */
    public function digitalMovement(): float
    {
        return (float) $this->payments()
            ->whereIn('method', self::DIGITAL_METHODS)
            ->sum('amount');
    }

    /**
     * Variance for an open shift, computed live. Once closed, the stored value
     * wins — a later edit to the ledger must not silently rewrite history.
     */
    public function currentVariance(): ?float
    {
        if (! $this->isOpen()) {
            return $this->variance === null ? null : (float) $this->variance;
        }

        return $this->counted_cash === null
            ? null
            : round((float) $this->counted_cash - $this->expectedCash(), 2);
    }

    /** Short, over, or square — the only three answers an owner cares about. */
    public function varianceLabel(): string
    {
        $variance = $this->currentVariance();

        return match (true) {
            $variance === null => 'Not counted',
            abs($variance) < 0.01 => 'Square',
            $variance < 0 => 'Short',
            default => 'Over',
        };
    }
}
