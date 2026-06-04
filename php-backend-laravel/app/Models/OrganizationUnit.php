<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class OrganizationUnit extends Model
{
    use HasFactory;

    protected $table = 'organization_units';

    protected $fillable = [
        'name',
        'type',
        'parent_id',
        'active',
    ];
}
