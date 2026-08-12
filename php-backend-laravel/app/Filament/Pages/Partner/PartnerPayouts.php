<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use App\Models\PartnerPayoutAccount;
use App\Models\PayoutBatch;
use App\Services\PartnerSettlement;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;

/**
 * Payouts — the partner's settlement home. Shows the balance (available /
 * pending / already settled), the settlement destination the partner enters
 * themselves (bank or UPI), and the history of payout batches Haraan has sent.
 *
 * MVP: the partner manages their account and reads their history; batches are
 * created and marked paid by admin in /control (no self-serve withdrawal or
 * gateway transfer yet). Lives only in the /partner console.
 */
class PartnerPayouts extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $title = 'Payouts';

    protected static ?string $navigationLabel = 'Payouts';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.partner.payouts';

    // ---- Settlement-account form (hand-rolled Livewire props) ----------------

    public bool $editing = false;

    public string $method = 'upi';

    public ?string $account_holder = null;

    public ?string $bank_name = null;

    public ?string $account_number = null;

    public ?string $ifsc_code = null;

    public ?string $upi_vpa = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'partner'
            && $user !== null
            && $user->hasPartnerPermission('reports');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $account = $this->account();

        if ($account) {
            // Non-secret context prefills; the destination itself (account number /
            // IFSC / UPI id) never gets painted back into the form — changing where
            // money goes means deliberately re-entering it.
            $this->method = $account->method ?: 'upi';
            $this->account_holder = $account->account_holder;
            $this->bank_name = $account->bank_name;
            $this->account_number = null;
            $this->ifsc_code = null;
            $this->upi_vpa = null;
        } else {
            // No destination yet — open straight into the form.
            $this->editing = true;
        }
    }

    public function getHeader(): ?View
    {
        return view('filament.pages.partner.payouts-header');
    }

    private function partnerId(): int
    {
        return (int) auth()->user()->effectivePartnerId();
    }

    public function account(): ?PartnerPayoutAccount
    {
        return PartnerPayoutAccount::query()
            ->where('partner_id', $this->partnerId())
            ->first();
    }

    public function startEditing(): void
    {
        $this->editing = true;
    }

    public function cancelEditing(): void
    {
        if ($this->account() === null) {
            return; // nothing to fall back to; keep the form open
        }
        $this->resetErrorBag();
        $this->mount();
        $this->editing = false;
    }

    public function save(): void
    {
        $rules = [
            'method' => ['required', 'in:bank,upi'],
            'account_holder' => ['required', 'string', 'min:2', 'max:120'],
        ];

        if ($this->method === 'upi') {
            $rules['upi_vpa'] = ['required', 'string', 'regex:/^[\w.\-]{2,}@[a-zA-Z]{2,}$/'];
        } else {
            $rules['bank_name'] = ['required', 'string', 'max:120'];
            $rules['account_number'] = ['required', 'string', 'regex:/^\d{6,18}$/'];
            $rules['ifsc_code'] = ['required', 'string', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'];
        }

        $data = $this->validate($rules, [
            'upi_vpa.regex' => 'Enter a valid UPI ID, e.g. name@okhdfc.',
            'account_number.regex' => 'Enter a valid account number (digits only).',
            'ifsc_code.regex' => 'Enter a valid IFSC code, e.g. HDFC0001234.',
        ]);

        // Only persist the fields for the chosen method; blank the other side so a
        // switched destination never keeps a stale bank/UPI on file.
        $payload = [
            'method' => $this->method,
            'account_holder' => trim((string) $this->account_holder),
            'bank_name' => $this->method === 'bank' ? trim((string) $this->bank_name) : null,
            'account_number' => $this->method === 'bank' ? preg_replace('/\s+/', '', (string) $this->account_number) : null,
            'ifsc_code' => $this->method === 'bank' ? strtoupper(trim((string) $this->ifsc_code)) : null,
            'upi_vpa' => $this->method === 'upi' ? strtolower(trim((string) $this->upi_vpa)) : null,
        ];

        PartnerPayoutAccount::updateOrCreate(
            ['partner_id' => $this->partnerId()],
            $payload + [
                // Editing the destination clears prior verification — admin re-checks.
                'verified_at' => null,
            ],
        );

        $this->editing = false;

        Notification::make()
            ->title('Settlement account saved')
            ->body('Haraan will verify it before your next payout.')
            ->success()
            ->send();
    }

    // ---- Balance -------------------------------------------------------------

    /**
     * Balance card figures.
     *
     * @return array<string, mixed>
     */
    public function getBalance(): array
    {
        // Shared with the admin settlement screen — the partner and the finance
        // desk must never be looking at two different "available" numbers. Scoped
        // to the owner (effectivePartnerId), not to a desk person's venue subset:
        // the money belongs to the account, however narrow the viewer's remit.
        $s = app(PartnerSettlement::class)->summary($this->partnerId());

        return [
            'available'   => $this->inr($s['available']),
            'settled'     => $this->inr($s['settled']),
            'collected'   => $this->inr($s['collected']),
            'inFlight'    => $this->inr($s['inFlight']),
            'hasInFlight' => $s['inFlight'] > 0,
            'hasAvail'    => $s['available'] > 0,
            'nextDate'    => $this->nextSettlementDate(),
        ];
    }

    /** Weekly cadence: next Monday. Display-only for the MVP. */
    private function nextSettlementDate(): string
    {
        return now()->next('Monday')->format('D, d M');
    }

    /**
     * Payout history — the batches Haraan has sent this partner.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBatches(): array
    {
        return PayoutBatch::query()
            ->where('partner_id', $this->partnerId())
            ->latest()
            ->limit(24)
            ->get()
            ->map(function (PayoutBatch $b): array {
                $paid = $b->isPaid();
                $failed = strtolower((string) $b->status) === 'failed';

                return [
                    'amount'    => $this->inr((float) $b->amount),
                    'status'    => $paid ? 'Paid' : ($failed ? 'Failed' : 'Processing'),
                    'tone'      => $paid ? 'ok' : ($failed ? 'bad' : 'warn'),
                    'reference' => $b->reference,
                    'period'    => $b->period_start && $b->period_end
                        ? $b->period_start->format('d M') . ' – ' . $b->period_end->format('d M')
                        : null,
                    'date'      => ($b->processed_at ?? $b->created_at)?->format('d M Y') ?? '',
                ];
            })->all();
    }

    /** ₹1,84,290 — Indian grouping, whole rupees. */
    private function inr(float $n): string
    {
        $n = (int) round($n);
        $str = (string) abs($n);
        if (strlen($str) <= 3) {
            return '₹' . $str;
        }
        $last3 = substr($str, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($str, 0, -3));

        return '₹' . $rest . ',' . $last3;
    }
}
