<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The official Form 16A for one partner and quarter, downloaded by finance from TRACES
 * and uploaded in /control. Stored on the private `local` disk and only ever served to
 * the partner it belongs to.
 */
final class PartnerTdsCertificate extends Model
{
    protected $fillable = ['partner_id', 'financial_year', 'quarter', 'certificate_no', 'file_path', 'uploaded_by_id'];

    protected $casts = ['quarter' => 'integer'];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }
}
