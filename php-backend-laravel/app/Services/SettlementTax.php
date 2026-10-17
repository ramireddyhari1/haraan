<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PartnerTaxProfile;
use App\Models\PayoutBatch;
use App\Models\User;
use App\Support\GstStates;
use App\Support\PlatformRules;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The tax arithmetic of a partner settlement, in one place, so the amount finance sends,
 * the balance the partner sees and the documents they download can never disagree.
 *
 *   gross                        what leaves the partner's available balance
 *   − commission                 Haraan's settlement commission (rate set per settlement)
 *   − GST on commission          only while Haraan has a GSTIN (/control → Platform rules)
 *   − TDS, section 194-O         on gross; a higher rate when the partner has no PAN
 *   − GST TCS, section 52        on gross; only when the partner has a GSTIN
 *   = net payout                 stored as `amount`, what reaches the bank
 */
final class SettlementTax
{
    /**
     * The rates a new settlement for this partner starts with. Finance may still change
     * them per settlement, but {@see compute()} enforces the two legal limits regardless.
     *
     * @return array{commission_gst_percent: float, tds_percent: float, tcs_percent: float, notes: list<string>}
     */
    public function defaultRates(int $partnerId): array
    {
        $profile = $this->profile($partnerId);
        $notes = [];

        $gst = $this->issuerHasGstin() ? PlatformRules::float('invoicing.commission_gst_percent') : 0.0;
        if (! $this->issuerHasGstin()) {
            $notes[] = 'No commission GST: Haraan’s GSTIN isn’t set in Platform rules.';
        }

        $tds = PlatformRules::float('invoicing.tds_percent');
        if ($tds > 0 && ! $profile?->hasPan()) {
            $tds = PlatformRules::float('invoicing.tds_no_pan_percent');
            $notes[] = 'Higher TDS: this partner has no PAN on file.';
        }

        $tcs = $profile?->hasGstin() ? PlatformRules::float('invoicing.tcs_percent') : 0.0;
        if (! $profile?->hasGstin() && PlatformRules::float('invoicing.tcs_percent') > 0) {
            $notes[] = 'No GST TCS: this partner has no GSTIN.';
        }

        return ['commission_gst_percent' => $gst, 'tds_percent' => $tds, 'tcs_percent' => $tcs, 'notes' => $notes];
    }

    /**
     * Every amount, from the gross and the rates. Rates are clamped, GST is zero without a
     * Haraan GSTIN, and TCS is zero for a partner without a GSTIN — whatever the form sent.
     *
     * @return array{gross_amount: float, commission_percent: float, commission_amount: float, commission_gst_percent: float, commission_gst_amount: float, tds_percent: float, tds_amount: float, tcs_percent: float, tcs_amount: float, amount: float}
     */
    public function compute(int $partnerId, float $gross, float $commissionPct, float $gstPct, float $tdsPct, float $tcsPct): array
    {
        $gross = round(max(0.0, $gross), 2);
        $commissionPct = self::clamp($commissionPct, 99.99);
        $gstPct = $this->issuerHasGstin() ? self::clamp($gstPct, 28) : 0.0;
        $tdsPct = self::clamp($tdsPct, 20);
        $tcsPct = $this->profile($partnerId)?->hasGstin() ? self::clamp($tcsPct, 5) : 0.0;

        $commission = round($gross * $commissionPct / 100, 2);
        $gst = round($commission * $gstPct / 100, 2);
        $tds = round($gross * $tdsPct / 100, 2);
        $tcs = round($gross * $tcsPct / 100, 2);

        return [
            'gross_amount' => $gross,
            'commission_percent' => $commissionPct,
            'commission_amount' => $commission,
            'commission_gst_percent' => $gstPct,
            'commission_gst_amount' => $gst,
            'tds_percent' => $tdsPct,
            'tds_amount' => $tds,
            'tcs_percent' => $tcsPct,
            'tcs_amount' => $tcs,
            'amount' => round(max(0.0, $gross - $commission - $gst - $tds - $tcs), 2),
        ];
    }

    /**
     * Freeze the issuer and partner details on a new settlement, and give it an invoice
     * number when Haraan kept a commission. Called from PayoutBatch::creating.
     */
    public function stamp(PayoutBatch $b): void
    {
        if ($b->tax_snapshot === null) {
            $b->tax_snapshot = $this->snapshot((int) $b->partner_id);
        }

        if ($b->invoice_number === null && (float) $b->commission_amount > 0) {
            $b->invoice_number = $this->nextInvoiceNumber(now());
        }
    }

    /** Re-read the billing details onto an existing settlement (finance's "Refresh details"). */
    public function restamp(PayoutBatch $b): void
    {
        $b->tax_snapshot = $this->snapshot((int) $b->partner_id);
        if ($b->invoice_number === null && (float) $b->commission_amount > 0) {
            $b->invoice_number = $this->nextInvoiceNumber($b->created_at ?? now());
        }
        $b->save();
    }

    /** @return array{issuer: array<string, ?string>, partner: array<string, ?string>} */
    public function snapshot(int $partnerId): array
    {
        $partner = User::query()->find($partnerId);
        $profile = $this->profile($partnerId);
        $gstin = $profile?->gstin ? strtoupper($profile->gstin) : null;

        return [
            'issuer' => [
                'legal_name' => PlatformRules::string('invoicing.legal_name'),
                'gstin' => PlatformRules::string('invoicing.gstin') ?: null,
                'pan' => PlatformRules::string('invoicing.pan') ?: null,
                'tan' => PlatformRules::string('invoicing.tan') ?: null,
                'address' => PlatformRules::string('invoicing.address') ?: null,
                'state_code' => PlatformRules::string('invoicing.state_code'),
                'sac' => PlatformRules::string('invoicing.sac_code'),
                'footer' => PlatformRules::string('invoicing.footer_note') ?: null,
            ],
            'partner' => [
                'name' => $partner?->name,
                'legal_name' => $profile?->legal_name ?: $partner?->name,
                'gstin' => $gstin,
                'pan' => $profile?->pan ? strtoupper($profile->pan) : null,
                'address' => $profile?->address,
                // A GSTIN names its own state; otherwise what the partner told us; otherwise
                // assume they're local to Haraan.
                'state_code' => GstStates::fromGstin($gstin) ?? $profile?->state_code ?? PlatformRules::string('invoicing.state_code'),
                'email' => $partner?->email,
                'phone' => $partner?->phone,
            ],
        ];
    }

    /** PREFIX/26-27/00042 — sequential within a financial year. */
    public function nextInvoiceNumber(CarbonInterface $when): string
    {
        $base = PlatformRules::string('invoicing.invoice_prefix') . '/' . self::shortFy($when) . '/';

        return DB::transaction(function () use ($base): string {
            $last = PayoutBatch::query()
                ->where('invoice_number', 'like', $base . '%')
                ->lockForUpdate()
                ->pluck('invoice_number')
                ->map(fn (string $n): int => (int) substr($n, strlen($base)))
                ->max() ?? 0;

            return $base . str_pad((string) ($last + 1), 5, '0', STR_PAD_LEFT);
        });
    }

    public function profile(int $partnerId): ?PartnerTaxProfile
    {
        return PartnerTaxProfile::query()->where('partner_id', $partnerId)->first();
    }

    public function issuerHasGstin(): bool
    {
        return PlatformRules::string('invoicing.gstin') !== '';
    }

    /** "2026-27" for any date from 1 Apr 2026 to 31 Mar 2027. */
    public static function financialYear(CarbonInterface $d): string
    {
        $start = $d->month >= 4 ? $d->year : $d->year - 1;

        return $start . '-' . substr((string) ($start + 1), -2);
    }

    /** "26-27". */
    public static function shortFy(CarbonInterface $d): string
    {
        return substr(self::financialYear($d), 2);
    }

    /** 1 = Apr–Jun, 2 = Jul–Sep, 3 = Oct–Dec, 4 = Jan–Mar. */
    public static function quarter(CarbonInterface $d): int
    {
        return intdiv(($d->month + 8) % 12, 3) + 1;
    }

    /** "Q2 · Jul–Sep 2026" */
    public static function quarterLabel(string $fy, int $q): string
    {
        $start = (int) substr($fy, 0, 4);
        $labels = [1 => ['Apr–Jun', $start], 2 => ['Jul–Sep', $start], 3 => ['Oct–Dec', $start], 4 => ['Jan–Mar', $start + 1]];
        [$months, $year] = $labels[$q];

        return 'Q' . $q . ' · ' . $months . ' ' . $year;
    }

    /** @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon} first and last moment of a quarter */
    public static function quarterRange(string $fy, int $q): array
    {
        $start = (int) substr($fy, 0, 4);
        $from = match ($q) {
            1 => \Illuminate\Support\Carbon::create($start, 4, 1),
            2 => \Illuminate\Support\Carbon::create($start, 7, 1),
            3 => \Illuminate\Support\Carbon::create($start, 10, 1),
            default => \Illuminate\Support\Carbon::create($start + 1, 1, 1),
        };

        return [$from->copy()->startOfDay(), $from->copy()->addMonths(3)->subDay()->endOfDay()];
    }

    private static function clamp(float $v, float $max): float
    {
        return round(min($max, max(0.0, $v)), 2);
    }
}
