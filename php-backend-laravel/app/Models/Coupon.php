<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Coupon extends Model
{
    use HasFactory;

    protected $fillable = ['code','discount','max_uses','uses','active'];

    protected $casts = [
        'discount' => 'float',
        'max_uses' => 'integer',
        'uses' => 'integer',
        'active' => 'boolean',
    ];
}
