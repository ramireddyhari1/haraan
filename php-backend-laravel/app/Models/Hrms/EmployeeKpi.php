<?php

declare(strict_types=1);

namespace App\Models\Hrms;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeeKpi extends Model
{
    use HasFactory;

    protected $table = 'employee_kpis';

    protected $fillable = [
        'employee_profile_id',
        'period',
        'reviewer_id',
        'punctuality_rating',
        'task_completion_rating',
        'customer_service_rating',
        'teamwork_rating',
        'overall_score',
        'achievements',
        'areas_for_improvement',
        'manager_feedback',
    ];

    protected $casts = [
        'punctuality_rating' => 'decimal:1',
        'task_completion_rating' => 'decimal:1',
        'customer_service_rating' => 'decimal:1',
        'teamwork_rating' => 'decimal:1',
        'overall_score' => 'decimal:1',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
