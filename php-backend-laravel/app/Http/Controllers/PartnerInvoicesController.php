<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PartnerTaxProfile;
use App\Models\PartnerTdsCertificate;
use App\Models\PayoutBatch;
use App\Models\User;
use App\Services\PartnerDocuments;
use App\Services\SettlementTax;
use App\Support\GstStates;
use App\Support\PartnerAppShell;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Partner invoices — the settlement statement, commission tax invoice, TDS / TCS
 * certificate, quarterly TDS summary and Form 16A for each settlement.
 *
 * Three doors, one rule set:
 *  - /api/partner/invoices…      the Android app (JWT); documents open in a WebView and print to PDF
 *  - /partner/invoice-docs/…     the partner web console (session)
 *  - the same web paths for finance staff, who may open any partner's documents from /control
 * A partner only ever reaches their own owner's documents, and needs the Reports permission —
 * the same gate as Payouts, since these carry the same money.
 */
final class PartnerInvoicesController extends Controller
{
    public function __construct(private readonly PartnerDocuments $docs) {}

    // ── App API ─────────────────────────────────────────────────────────────────

    /** GET /api/partner/invoices — every document, the quarters, and the tax details on file. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $partnerId = $user->effectivePartnerId();
        $profile = PartnerTaxProfile::query()->where('partner_id', $partnerId)->first();

        return response()->json([
            'enabled' => PartnerDocuments::enabled(),
            ...(PartnerDocuments::enabled() ? $this->docs->index($partnerId) : ['settlements' => [], 'quarters' => []]),
            'tax_profile' => [
                'legal_name' => $profile?->legal_name,
                'gstin' => $profile?->gstin,
                'pan' => $profile?->pan,
                'address' => $profile?->address,
                'state_code' => $profile?->state_code,
                'state' => GstStates::name($profile?->state_code),
            ],
            'states' => collect(GstStates::OPTIONS)->map(fn (string $name, string $code): array => ['code' => $code, 'name' => $name])->values(),
            'can_edit_tax' => $user->parent_partner_id === null,
        ]);
    }

    /** POST /api/partner/tax-profile — the owner sets who invoices are billed to. */
    public function saveTaxProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->parent_partner_id !== null) {
            return response()->json(['message' => 'Only the venue owner can change tax details.'], 403);
        }

        $profile = self::writeTaxProfile($user->effectivePartnerId(), $request->all(), $user);

        return response()->json(['status' => 'ok', 'tax_profile' => $profile->only(['legal_name', 'gstin', 'pan', 'address', 'state_code'])]);
    }

    /** GET /api/partner/invoices/{id}/{doc} */
    public function apiDocument(Request $request, int $id, string $doc): Response
    {
        return $this->document($request->user()->effectivePartnerId(), $id, $doc, $request->boolean('bare'));
    }

    /** GET /api/partner/invoices/quarter/{fy}/{q} */
    public function apiQuarter(Request $request, string $fy, int $q): Response
    {
        return $this->quarter($request->user()->effectivePartnerId(), $fy, $q, $request->boolean('bare'));
    }

    /** GET /api/partner/invoices/form16a/{id} */
    public function apiForm16a(Request $request, int $id): Response
    {
        return $this->form16a($request->user()->effectivePartnerId(), $id);
    }

    // ── Web (partner console session, or finance staff) ────────────────────────

    public function webDocument(Request $request, int $id, string $doc): Response
    {
        $batch = PayoutBatch::query()->findOrFail($id);

        return $this->document($this->webPartnerId($request, (int) $batch->partner_id), $id, $doc, false);
    }

    public function webQuarter(Request $request, int $partner, string $fy, int $q): Response
    {
        return $this->quarter($this->webPartnerId($request, $partner), $fy, $q, false);
    }

    public function webForm16a(Request $request, int $id): Response
    {
        $cert = PartnerTdsCertificate::query()->findOrFail($id);

        return $this->form16a($this->webPartnerId($request, (int) $cert->partner_id), $id);
    }

    /**
     * Which partner's documents this web visitor may read: finance staff may read the one
     * asked for; a signed-in partner with Reports only their own.
     */
    private function webPartnerId(Request $request, int $asked): int
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if ($user->canManage('finance')) {
            return $asked;
        }

        abort_unless(PartnerAppShell::user() !== null && $user->hasPartnerPermission('reports'), 403);

        return $user->effectivePartnerId();
    }

    // ── Shared ────────────────────────────────────────────────────────────────

    private function document(int $partnerId, int $id, string $doc, bool $bare): Response
    {
        abort_unless(PartnerDocuments::enabled(), 404);
        abort_unless(in_array($doc, PartnerDocuments::BATCH_DOCS, true), 404);

        // Scoped by owner: another partner's settlement id is simply not found.
        $batch = PayoutBatch::query()->where('partner_id', $partnerId)->findOrFail($id);
        abort_unless(in_array($doc, $this->docs->docsFor($batch), true), 404);

        if ($doc === 'csv') {
            return $this->docs->csv($batch);
        }

        return response($this->docs->render($batch, $doc, ! $bare), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex',
            // The app saves the PDF under this name.
            'X-Document-Name' => $this->docs->fileName($batch, $doc),
        ]);
    }

    private function quarter(int $partnerId, string $fy, int $q, bool $bare): Response
    {
        abort_unless(PartnerDocuments::enabled(), 404);
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $fy) === 1 && $q >= 1 && $q <= 4, 404);

        return response($this->docs->renderQuarter($partnerId, $fy, $q, ! $bare), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex',
            'X-Document-Name' => 'Haraan-TDS-Summary-' . $fy . '-Q' . $q . '.pdf',
        ]);
    }

    private function form16a(int $partnerId, int $id): Response
    {
        abort_unless(PartnerDocuments::enabled(), 404);
        $cert = PartnerTdsCertificate::query()->where('partner_id', $partnerId)->findOrFail($id);
        abort_unless(Storage::disk('local')->exists($cert->file_path), 404);

        return Storage::disk('local')->download(
            $cert->file_path,
            'Form16A-' . $cert->financial_year . '-Q' . $cert->quarter . '.pdf',
            ['Cache-Control' => 'private, no-store'],
        );
    }

    /**
     * Validate and save a partner's tax details — the one write path, shared with /control.
     *
     * @param  array<string, mixed>  $input
     */
    public static function writeTaxProfile(int $partnerId, array $input, User $by): PartnerTaxProfile
    {
        $clean = [
            'legal_name' => trim((string) ($input['legal_name'] ?? '')) ?: null,
            'gstin' => strtoupper(preg_replace('/\s+/', '', (string) ($input['gstin'] ?? ''))) ?: null,
            'pan' => strtoupper(preg_replace('/\s+/', '', (string) ($input['pan'] ?? ''))) ?: null,
            'address' => trim((string) ($input['address'] ?? '')) ?: null,
            'state_code' => ($input['state_code'] ?? null) ?: null,
        ];

        validator($clean, [
            'legal_name' => ['nullable', 'string', 'max:160'],
            'gstin' => ['nullable', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][A-Z\d]Z[A-Z\d]$/'],
            'pan' => ['nullable', 'regex:/^[A-Z]{5}\d{4}[A-Z]$/'],
            'address' => ['nullable', 'string', 'max:300'],
            'state_code' => ['nullable', Rule::in(array_keys(GstStates::OPTIONS))],
        ], [
            'gstin.regex' => 'That isn’t a valid GSTIN — 15 characters, like 37ABCDE1234F1Z5.',
            'pan.regex' => 'That isn’t a valid PAN — 10 characters, like ABCDE1234F.',
        ])->after(function ($v) use ($clean): void {
            // A GSTIN carries the PAN inside it; two that disagree would put tax on the wrong person.
            if ($clean['gstin'] && $clean['pan'] && substr($clean['gstin'], 2, 10) !== $clean['pan']) {
                $v->errors()->add('pan', 'This PAN doesn’t match the one inside your GSTIN.');
            }
        })->validate();

        // The GSTIN names the state; keep them consistent.
        if ($clean['gstin']) {
            $clean['state_code'] = GstStates::fromGstin($clean['gstin']) ?? $clean['state_code'];
            $clean['pan'] ??= substr($clean['gstin'], 2, 10);
        }

        $profile = PartnerTaxProfile::query()->updateOrCreate(
            ['partner_id' => $partnerId],
            $clean + ['updated_by_id' => $by->id],
        );
        self::refreshOpenSnapshots($partnerId);

        return $profile;
    }

    /** Settlements that haven't been paid yet pick up the new billing details. */
    public static function refreshOpenSnapshots(int $partnerId): void
    {
        $tax = app(SettlementTax::class);
        PayoutBatch::query()
            ->where('partner_id', $partnerId)
            ->whereIn(\Illuminate\Support\Facades\DB::raw('lower(status)'), ['processing', 'pending'])
            ->get()
            ->each(fn (PayoutBatch $b) => $tax->restamp($b));
    }
}
