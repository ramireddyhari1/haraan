<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Standing Contract: Parent recurring contract entity representing a regular group/club.
 *
 * @property int $id
 * @property int $venue_id
 * @property int $venue_court_id
 * @property int|null $user_id
 * @property string $customer_name
 * @property string $customer_phone
 * @property string|null $sport
 * @property string $day_of_week
 * @property string $start_time
 * @property string $end_time
 * @property int $duration_minutes
 * @property float $price_per_session
 * @property float|null $monthly_package_price
 * @property float $security_deposit
 * @property float $advance_paid
 * @property float $balance_due
 * @property \Illuminate\Support\Carbon $active_from
 * @property \Illuminate\Support\Carbon|null $active_until
 * @property string $status
 * @property bool $auto_renew
 * @property int $max_members
 * @property string|null $notes
 * @property int $consecutive_missed_sessions
 * @property int $total_sessions_count
 * @property int $attended_sessions_count
 * @property float $attendance_rate
 * @property bool $is_at_risk
 * @property string|null $risk_reason
 * @property int|null $created_by
 */
class StandingContract extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_AT_RISK = 'at_risk';
    public const STATUS_TERMINATED = 'terminated';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'venue_id',
        'venue_court_id',
        'user_id',
        'customer_name',
        'customer_phone',
        'sport',
        'day_of_week',
        'start_time',
        'end_time',
        'duration_minutes',
        'price_per_session',
        'monthly_package_price',
        'security_deposit',
        'advance_paid',
        'balance_due',
        'active_from',
        'active_until',
        'status',
        'auto_renew',
        'max_members',
        'notes',
        'consecutive_missed_sessions',
        'total_sessions_count',
        'attended_sessions_count',
        'attendance_rate',
        'is_at_risk',
        'risk_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'active_from' => 'date',
            'active_until' => 'date',
            'duration_minutes' => 'integer',
            'price_per_session' => 'decimal:2',
            'monthly_package_price' => 'decimal:2',
            'security_deposit' => 'decimal:2',
            'advance_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'auto_renew' => 'boolean',
            'max_members' => 'integer',
            'consecutive_missed_sessions' => 'integer',
            'total_sessions_count' => 'integer',
            'attended_sessions_count' => 'integer',
            'attendance_rate' => 'decimal:2',
            'is_at_risk' => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(VenueCourt::class, 'venue_court_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(StandingContractSession::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'standing_contract_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(StandingContractLog::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StandingContractPayment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_AT_RISK]);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_AT_RISK], true);
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function isTerminated(): bool
    {
        return $this->status === self::STATUS_TERMINATED;
    }

    /**
     * Compute and refresh attendance and churn metrics.
     */
    public function recalculateMetrics(): self
    {
        $sessions = $this->sessions()
            ->whereDate('session_date', '<=', today())
            ->whereNotIn('attendance_status', ['scheduled', 'skipped_holiday', 'skipped_manual'])
            ->get();

        $total = $sessions->count();
        $attended = $sessions->where('attendance_status', 'present')->count();
        $rate = $total > 0 ? round(($attended / $total) * 100, 2) : 100.0;

        // Calculate consecutive missed streak from recent sessions
        $recentSessions = $this->sessions()
            ->whereDate('session_date', '<=', today())
            ->whereNotIn('attendance_status', ['scheduled', 'skipped_holiday', 'skipped_manual'])
            ->orderByDesc('session_date')
            ->take(5)
            ->get();

        $missedStreak = 0;
        foreach ($recentSessions as $s) {
            if ($s->attendance_status === 'absent' || str_starts_with($s->attendance_status, 'cancelled_customer')) {
                $missedStreak++;
            } else {
                break;
            }
        }

        $atRisk = $missedStreak >= 2 || ($total >= 4 && $rate < 50.0);
        $riskReason = null;
        if ($missedStreak >= 2) {
            $riskReason = "Customer missed {$missedStreak} sessions in a row.";
        } elseif ($total >= 4 && $rate < 50.0) {
            $riskReason = "Low attendance rate ({$rate}%).";
        }

        $this->forceFill([
            'total_sessions_count' => $total,
            'attended_sessions_count' => $attended,
            'attendance_rate' => $rate,
            'consecutive_missed_sessions' => $missedStreak,
            'is_at_risk' => $atRisk,
            'risk_reason' => $riskReason,
            'status' => $this->status === self::STATUS_PAUSED || $this->status === self::STATUS_TERMINATED
                ? $this->status
                : ($atRisk ? self::STATUS_AT_RISK : self::STATUS_ACTIVE),
        ])->save();

        return $this;
    }
}
