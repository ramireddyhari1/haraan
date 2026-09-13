<?php

declare(strict_types=1);

namespace App\Models\Hrms;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class EmployeeDelegation extends Model
{
    use HasFactory;

    protected $table = 'employee_delegations';

    protected $fillable = [
        'delegator_id',
        'delegatee_id',
        'start_date',
        'end_date',
        'scope',
        'max_approval_tier',
        'is_active',
        'reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'max_approval_tier' => 'integer',
        'is_active' => 'boolean',
    ];

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    public function delegatee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegatee_id');
    }

    /**
     * Check if delegation is active on given date.
     */
    public function isActiveOn(?Carbon $date = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $d = $date ?? Carbon::today();
        return $d->gte($this->start_date) && $d->lte($this->end_date);
    }

    /**
     * Check if requested action scope is permitted.
     */
    public function permitsScope(string $requestedScope, int $tier = 1): bool
    {
        if (! $this->isActiveOn()) {
            return false;
        }

        if ($tier > $this->max_approval_tier) {
            return false;
        }

        if ($this->scope === 'all' || $this->scope === $requestedScope) {
            return true;
        }

        return false;
    }

    /**
     * Guardrail validator to prevent invalid or circular delegations.
     */
    public static function validateGuardrails(
        int $delegatorId,
        int $delegateeId,
        string $startDate,
        string $endDate,
        int $maxTier = 1
    ): void {
        if ($delegatorId === $delegateeId) {
            throw new InvalidArgumentException('Self-delegation is strictly prohibited.');
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        if ($end->lt($start)) {
            throw new InvalidArgumentException('Delegation end date cannot precede start date.');
        }

        if ($maxTier > 2 || $maxTier < 1) {
            throw new InvalidArgumentException('Max approval tier must be between 1 and 2.');
        }

        // Circular delegation check: Has delegatee delegated to delegator in overlapping period?
        $circular = self::where('delegator_id', $delegateeId)
            ->where('delegatee_id', $delegatorId)
            ->where('is_active', true)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                  ->orWhereBetween('end_date', [$start, $end])
                  ->orWhere(function ($sub) use ($start, $end) {
                      $sub->where('start_date', '<=', $start)
                          ->where('end_date', '>=', $end);
                  });
            })
            ->exists();

        if ($circular) {
            throw new InvalidArgumentException('Circular delegation detected: The target delegatee has already delegated signing authority back to you during an overlapping window.');
        }
    }
}
