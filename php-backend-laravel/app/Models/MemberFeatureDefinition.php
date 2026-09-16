<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Display copy for a member feature. The key and type are fixed by
 * App\Support\Membership\MemberFeature; /control edits only how it reads.
 *
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property string|null $unit
 * @property bool $is_visible
 */
final class MemberFeatureDefinition extends Model
{
    protected $table = 'member_features';

    protected $fillable = ['key', 'name', 'description', 'type', 'unit', 'sort', 'is_visible'];

    protected $casts = [
        'sort' => 'integer',
        'is_visible' => 'boolean',
    ];
}
