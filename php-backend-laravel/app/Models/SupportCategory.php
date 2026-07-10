<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An issue topic the user picks before starting a support conversation.
 * Admin-managed from the control panel — see the create_support_categories
 * migration for why the icon is an emoji rather than a drawable key.
 *
 * @property int         $id
 * @property string      $label
 * @property string|null $icon
 * @property int         $sort_order
 * @property bool        $is_active
 */
class SupportCategory extends Model
{
    protected $fillable = [
        'label',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ];
    }

    /** The picker's running order: what the app shows, in the order it shows it. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function threads(): HasMany
    {
        return $this->hasMany(SupportThread::class, 'category_id');
    }
}
