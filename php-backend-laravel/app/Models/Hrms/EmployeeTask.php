<?php

declare(strict_types=1);

namespace App\Models\Hrms;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeeTask extends Model
{
    use HasFactory;

    protected $table = 'employee_tasks';

    protected $fillable = [
        'title',
        'description',
        'employee_profile_id',
        'assigned_by',
        'venue_id',
        'priority',
        'status',
        'progress_percent',
        'checklist_items',
        'due_date',
        'completed_at',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'checklist_items' => 'array',
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
