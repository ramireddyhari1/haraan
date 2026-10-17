<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\PartnerTdsCertificate;
use App\Models\PayoutBatch;
use App\Support\BusinessClock;
use App\Support\GstStates;
use App\Support\PlatformRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The documents a partner downloads for their settlements — the Playo-style Invoices list.
 *
 * Per settlement:
 *   settlement   Settlement statement: gross, every deduction, net, UTR, and the period's bookings
 *   invoice      Tax invoice for Haraan's commission (+ CGST/SGST or IGST) — only when commission > 0
 *   tds          TDS / TCS deduction certificate — only when something was withheld
 *   csv          The period's bookings as a spreadsheet
 * Per quarter:
 *   quarterly    TDS summary for the quarter, plus the official Form 16A once finance uploads it
 *
 * Every figure comes off the stored settlement row and its frozen tax_snapshot — never
 * recomputed — so a document reads the same on the day it was issued and years later.
 * Failed settlements keep their documents, stamped CANCELLED, so the trail has no gaps.
 */
final class PartnerDocuments
{
    public const BATCH_DOCS = ['settlement', 'invoice', 'tds', 'csv'];

    public static function enabled(): bool
    {
        return PlatformRules::bool('invoicing.enabled');
    }

    /** Which documents a settlement has. @return list<string> */
    public function docsFor(PayoutBatch $b): array
    {
        $docs = ['settlement'];
        if ((float) $b->commission_amount > 0) {
            $docs[] = 'invoice';
        }
        if ((float) $b->tds_amount > 0 || (float) $b->tcs_amount > 0) {
            $docs[] = 'tds';
        }
        $docs[] = 'csv';

        return $docs;
    }

    /**
     * The whole Invoices list for one partner, newest first.
     *
     * @return array{settlements: list<array<string, mixed>>, quarters: list<array<string, mixed>>}
     */
    public function index(int $partnerId): array
    {
        $batches = PayoutBatch::query()->where('partner_id', $partnerId)->orderByDesc('created_at')->limit(200)->get();

        $settlements = $batches->map(fn (PayoutBatch $b): array => [
            'id' => $b->id,
            'date' => BusinessClock::local($b->created_at)->toDateString(),
            'month' => BusinessClock::local($b->created_at)->format('M Y'),
            'period' => $this->period($b),
            'status' => $this->statusLabel($b),
            'gross' => round($b->gross(), 2),
            'commission' => round((float) $b->commission_amount, 2),
            'commission_gst' => round((float) $b->commission_gst_amount, 2),
            'tds' => round((float) $b->tds_amount, 2),
            'tcs' => round((float) $b->tcs_amount, 2),
            'net' => round((float) $b->amount, 2),
            'invoice_number' => $b->invoice_number,
            'reference' => $b->reference,
            'docs' => $this->docsFor($b),
        ])->values()->all();

        // Quarters that withheld anything, plus any quarter finance uploaded a certificate for.
        $certs = PartnerTdsCertificate::query()->where('partner_id', $partnerId)->get()
            ->keyBy(fn (PartnerTdsCertificate $c): string => $c->financial_year . '#' . $c->quarter);

        $quarters = [];
        foreach ($batches as $b) {
            if ($b->isFailed() || ((float) $b->tds_amount <= 0 && (float) $b->tcs_amount <= 0)) {
                continue;
            }
            $d = BusinessClock::local($b->created_at);
            $key = SettlementTax::financialYear($d) . '#' . SettlementTax::quarter($d);
            $quarters[$key] ??= ['tds' => 0.0, 'tcs' => 0.0, 'gross' => 0.0, 'count' => 0];
            $quarters[$key]['tds'] += (float) $b->tds_amount;
            $quarters[$key]['tcs'] += (float) $b->tcs_amount;
            $quarters[$key]['gross'] += $b->gross();
            $quarters[$key]['count']++;
        }
        foreach ($certs->keys() as $key) {
            $quarters[$key] ??= ['tds' => 0.0, 'tcs' => 0.0, 'gross' => 0.0, 'count' => 0];
        }
        krsort($quarters);

        return [
            'settlements' => $settlements,
            'quarters' => collect($quarters)->map(function (array $q, string $key) use ($certs): array {
                [$fy, $quarter] = explode('#', $key);
                $cert = $certs->get($key);

                return [
                    'financial_year' => $fy,
                    'quarter' => (int) $quarter,
                    'label' => SettlementTax::quarterLabel($fy, (int) $quarter),
                    'settlements' => $q['count'],
                    'gross' => round($q['gross'], 2),
                    'tds' => round($q['tds'], 2),
                    'tcs' => round($q['tcs'], 2),
                    'form16a' => $cert === null ? null : ['id' => $cert->id, 'certificate_no' => $cert->certificate_no, 'uploaded' => $cert->created_at?->toDateString()],
                ];
            })->values()->all(),
        ];
    }

    /** The HTML of one settlement document. */
    public function render(PayoutBatch $b, string $doc, bool $chrome = true): string
    {
        $snap = $b->tax_snapshot ?? app(SettlementTax::class)->snapshot((int) $b->partner_id);
        $issuer = $snap['issuer'];
        $partner = $snap['partner'];
        $intra = ($issuer['state_code'] ?? null) === ($partner['state_code'] ?? null);
        $gst = (float) $b->commission_gst_amount;

        $data = self::common() + [
            'b' => $b,
            'doc' => $doc,
            'chrome' => $chrome,
            'issuer' => $issuer,
            'partner' => $partner,
            'issued' => BusinessClock::local($b->created_at),
            'period' => $this->period($b),
            'status' => $this->statusLabel($b),
            'cancelled' => $b->isFailed(),
            'intra' => $intra,
            'cgst' => $intra ? round($gst / 2, 2) : 0.0,
            'sgst' => $intra ? round($gst - round($gst / 2, 2), 2) : 0.0,
            'igst' => $intra ? 0.0 : $gst,
            'issuerState' => GstStates::name($issuer['state_code'] ?? null),
            'partnerState' => GstStates::name($partner['state_code'] ?? null),
            'fy' => SettlementTax::financialYear(BusinessClock::local($b->created_at)),
            'quarterLabel' => SettlementTax::quarterLabel(
                SettlementTax::financialYear(BusinessClock::local($b->created_at)),
                SettlementTax::quarter(BusinessClock::local($b->created_at)),
            ),
            'bookings' => $doc === 'settlement' ? $this->bookings($b)->take(300) : collect(),
            'bookingTotals' => $doc === 'settlement' ? $this->bookingTotals($b) : null,
        ];

        return view('partner-docs.' . $doc, $data)->render();
    }

    /** The HTML of a quarter's TDS summary. */
    public function renderQuarter(int $partnerId, string $fy, int $q, bool $chrome = true): string
    {
        [$from, $to] = SettlementTax::quarterRange($fy, $q);
        [$fromUtc] = BusinessClock::dayBoundsUtc($from->toDateString());
        [, $toUtc] = BusinessClock::dayBoundsUtc($to->toDateString());

        $batches = PayoutBatch::query()
            ->where('partner_id', $partnerId)
            ->whereBetween('created_at', [$fromUtc, $toUtc])
            ->orderBy('created_at')
            ->get()
            ->reject(fn (PayoutBatch $b): bool => $b->isFailed());

        // The latest settlement's frozen details, else today's.
        $snap = $batches->last()?->tax_snapshot ?? app(SettlementTax::class)->snapshot($partnerId);
        $cert = PartnerTdsCertificate::query()->where(['partner_id' => $partnerId, 'financial_year' => $fy, 'quarter' => $q])->first();

        return view('partner-docs.quarterly', self::common() + [
            'doc' => 'quarterly',
            'chrome' => $chrome,
            'issuer' => $snap['issuer'],
            'partner' => $snap['partner'],
            'fy' => $fy,
            'q' => $q,
            'label' => SettlementTax::quarterLabel($fy, $q),
            'batches' => $batches,
            'cert' => $cert,
            'cancelled' => false,
            'issued' => BusinessClock::now(),
        ])->render();
    }

    /** Formatters and the embedded logo every document uses. @return array<string, mixed> */
    private static function common(): array
    {
        static $logo = null;
        $logo ??= is_file(public_path('images/haraan-logo-blue.png'))
            ? 'data:image/png;base64,' . base64_encode((string) file_get_contents(public_path('images/haraan-logo-blue.png')))
            : '';

        return [
            'logo' => $logo,
            'inr' => fn ($v): string => '₹' . number_format((float) $v, 2),
            'pct' => fn ($v): string => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%',
        ];
    }

    /** The period's bookings as a CSV download. */
    public function csv(PayoutBatch $b): StreamedResponse
    {
        $rows = $this->bookings($b);
        $name = 'haraan-settlement-' . $b->id . '-bookings.csv';

        return response()->streamDownload(function () use ($rows, $b): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel reads UTF-8 (₹, names) only with a BOM
            fputcsv($out, ['Settlement', '#' . $b->id, 'Period', $this->period($b) ?? '']);
            fputcsv($out, []);
            fputcsv($out, ['Booking', 'Date', 'For', 'Customer', 'Channel', 'Status', 'Amount (INR)', 'Haraan fees & commission (INR)', 'Your share (INR)']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['ref'], $r['date'], $r['what'], $r['customer'], $r['channel'], $r['status'], number_format($r['amount'], 2, '.', ''), number_format($r['deduction'], 2, '.', ''), number_format($r['share'], 2, '.', '')]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** File name for a downloaded document, e.g. "Haraan-Tax-Invoice-HRN-26-27-00012.pdf". */
    public function fileName(PayoutBatch $b, string $doc): string
    {
        $tail = $doc === 'invoice' && $b->invoice_number
            ? preg_replace('/[^A-Za-z0-9]+/', '-', $b->invoice_number)
            : 'S' . $b->id;

        return 'Haraan-' . match ($doc) {
            'invoice' => 'Tax-Invoice',
            'tds' => 'TDS-Certificate',
            default => 'Settlement-Statement',
        } . '-' . $tail . '.pdf';
    }

    /**
     * Paid bookings inside the settlement's period. A settlement with no period lists none —
     * the statement then says so rather than guessing which bookings it covered.
     *
     * @return Collection<int, array{ref: string, date: string, what: string, customer: string, channel: string, status: string, amount: float, deduction: float, share: float}>
     */
    public function bookings(PayoutBatch $b): Collection
    {
        if ($b->period_start === null || $b->period_end === null) {
            return collect();
        }

        [$from] = BusinessClock::dayBoundsUtc($b->period_start->toDateString());
        [, $to] = BusinessClock::dayBoundsUtc($b->period_end->toDateString());

        return app(PartnerSettlement::class)->bookings((int) $b->partner_id)
            ->whereIn(DB::raw('lower(status)'), PartnerSettlement::PAID_BOOKING)
            ->whereBetween('created_at', [$from, $to])
            ->with(['venue:id,name', 'event:id,title'])
            ->orderBy('created_at')
            ->limit(5000)
            ->get()
            ->map(fn (Booking $k): array => [
                'ref' => 'B' . $k->id,
                'date' => BusinessClock::local($k->created_at)->format('d M Y'),
                'what' => $k->venue_id ? trim(($k->venue?->name ?? 'Venue') . ($k->slot_label ? ' · ' . $k->slot_label : '')) : ($k->event?->title ?? 'Event'),
                'customer' => $k->channel === 'offline' ? ($k->guest_name ?: 'Walk-in') : ($k->attendee_name ?: 'Online customer'),
                'channel' => $k->channel === 'offline' ? 'Desk' : 'Online',
                'status' => ucfirst(strtolower((string) $k->status)),
                'amount' => (float) $k->total_amount,
                'deduction' => (float) $k->host_deduction,
                'share' => max(0.0, (float) $k->total_amount - (float) $k->host_deduction),
            ]);
    }

    /** @return array{count: int, amount: float, deduction: float, share: float} */
    private function bookingTotals(PayoutBatch $b): array
    {
        $rows = $this->bookings($b);

        return [
            'count' => $rows->count(),
            'amount' => round($rows->sum('amount'), 2),
            'deduction' => round($rows->sum('deduction'), 2),
            'share' => round($rows->sum('share'), 2),
        ];
    }

    private function period(PayoutBatch $b): ?string
    {
        if ($b->period_start === null || $b->period_end === null) {
            return null;
        }

        return $b->period_start->format('d M Y') . ' – ' . $b->period_end->format('d M Y');
    }

    private function statusLabel(PayoutBatch $b): string
    {
        return $b->isPaid() ? 'Paid' : ($b->isFailed() ? 'Cancelled' : 'Processing');
    }
}
