<?php

declare(strict_types=1);

namespace App\Models\Hrms;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HrmsAnnouncement extends Model
{
    use HasFactory;

    protected $table = 'hrms_announcements';

    protected $fillable = [
        'title',
        'body',
        'priority',
        'audience',
        'partner_id',
        'venue_id',
        'published_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
