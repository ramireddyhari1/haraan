<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An issue topic the user picks before starting a support conversation.
 * Admin-managed from the control panel.
 *
 * @property int         $id
 * @property string      $label
 * @property string      $icon_key
 * @property string|null $subtitle
 * @property int         $sort_order
 * @property bool        $is_active
 */
class SupportCategory extends Model
{
    /**
     * Keys the app knows how to draw, as label => admin-facing name. The app
     * falls back to a chat bubble for anything it doesn't recognise, so adding a
     * key here before the matching app build ships is safe.
     */
    public const ICON_KEYS = [
        'ticket'  => 'Ticket',
        'card'    => 'Payment card',
        'cricket' => 'Cricket / sport',
        'venue'   => 'Venue / location',
        'account' => 'Account',
        'partner' => 'Partner / business',
        'event'   => 'Event / calendar',
        'chat'    => 'Chat bubble (default)',
    ];

    protected $fillable = [
        'label',
        'icon_key',
        'subtitle',
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
