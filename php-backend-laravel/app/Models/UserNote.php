<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An internal administrative note authored by a staff member on a user account.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $author_id
 * @property string $category
 * @property string|null $title
 * @property string $content
 * @property bool $is_pinned
 * @property bool $is_confidential
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read User $user
 * @property-read User|null $author
 */
class UserNote extends Model
{
    use HasFactory;

    protected $table = 'user_notes';

    protected $fillable = [
        'user_id',
        'author_id',
        'category',
        'title',
        'content',
        'is_pinned',
        'is_confidential',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_confidential' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (UserNote $note): void {
            if (! $note->author_id && auth()->check()) {
                $note->author_id = auth()->id();
            }
        });
    }

    public const CATEGORY_GENERAL = 'general';
    public const CATEGORY_SUPPORT = 'support';
    public const CATEGORY_RISK = 'risk';
    public const CATEGORY_FINANCIAL = 'financial';
    public const CATEGORY_MODERATION = 'moderation';
    public const CATEGORY_FRAUD_ALERT = 'fraud_alert';
    public const CATEGORY_VIP_PREFERENCE = 'vip_preference';
    public const CATEGORY_SUPPORT_ESCALATION = 'support_escalation';
    public const CATEGORY_ENFORCEMENT = 'enforcement';

    public const CATEGORIES = [
        self::CATEGORY_GENERAL            => 'General',
        self::CATEGORY_SUPPORT            => 'Customer Support',
        self::CATEGORY_RISK               => 'Risk & Security',
        self::CATEGORY_FINANCIAL          => 'Billing & Financial',
        self::CATEGORY_MODERATION         => 'Moderation & Conduct',
        self::CATEGORY_FRAUD_ALERT        => 'Fraud Alert',
        self::CATEGORY_VIP_PREFERENCE     => 'VIP Preference',
        self::CATEGORY_SUPPORT_ESCALATION => 'Support Escalation',
        self::CATEGORY_ENFORCEMENT        => 'Enforcement',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePinned(Builder $query): Builder
    {
        return $query->where('is_pinned', true);
    }

    public function scopeConfidential(Builder $query): Builder
    {
        return $query->where('is_confidential', true);
    }

    /**
     * Scope notes according to whether the operator may view confidential items.
     */
    public function scopeForOperator(Builder $query, ?User $operator): Builder
    {
        if ($operator === null) {
            return $query->where('is_confidential', false);
        }

        $canViewConfidential = $operator->isSuperAdmin() || $operator->hasRoleEither(['OPS']);

        return $canViewConfidential
            ? $query
            : $query->where('is_confidential', false);
    }
}
