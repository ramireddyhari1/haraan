<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $id
 * @property string $slug
 * @property string $title
 * @property string $body
 * @property \Carbon\Carbon $updated_at
 */
class LegalDocument extends Model
{
    use AuditsAdminChanges;

    protected $fillable = ['slug', 'title', 'body'];
}
