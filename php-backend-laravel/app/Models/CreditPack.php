<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A prepaid bundle of conversations, bought as a one-time order. Credits never
 * expire, so a pack bought for one busy month carries into the next.
 */
final class CreditPack extends Model
{
    protected $fillable = ['code', 'name', 'conversations', 'price_inr', 'is_active', 'sort'];

    protected $casts = [
        'conversations' => 'integer',
        'price_inr' => 'integer',
        'is_active' => 'boolean',
    ];

    public function amountPaise(): int
    {
        return $this->price_inr * 100;
    }
}
