<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who a partner's invoices are billed to: legal name, GSTIN, PAN and address. One per
 * partner owner. The PAN decides the TDS rate and the GSTIN decides whether GST TCS
 * applies, so {@see \App\Services\SettlementTax} reads this when a settlement is made.
 * The owner edits it in the app or /partner → Invoices; finance edits it in /control.
 */
final class PartnerTaxProfile extends Model
{
    protected $fillable = ['partner_id', 'legal_name', 'gstin', 'pan', 'address', 'state_code', 'updated_by_id'];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function hasPan(): bool
    {
        return filled($this->pan);
    }

    public function hasGstin(): bool
    {
        return filled($this->gstin);
    }
}
