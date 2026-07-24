<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use App\Models\CreditPack;
use App\Models\PartnerPlan;
use App\Models\PartnerSubscription;
use App\Services\PlanEntitlements;
use App\Services\RazorpayBilling;
use App\Services\RazorpayGateway;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

/**
 * "Plan & usage" — what the partner is on, what it unlocks, and how much of
 * their conversation quota is gone.
 *
 * Read-only for now: there's no self-serve upgrade until Razorpay subscriptions
 * land, so the page states plainly that changing plan means talking to Haraan
 * rather than dangling a button that does nothing.
 */
class PartnerPlanPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $title = 'Plan & usage';

    protected static ?string $navigationLabel = 'Plan & usage';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.partner.plan';

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

    private function partnerId(): int
    {
        return (int) auth()->user()->effectivePartnerId();
    }

    public function plan(): PartnerPlan
    {
        return app(PlanEntitlements::class)->plan($this->partnerId());
    }

    public function subscription(): ?PartnerSubscription
    {
        return app(PlanEntitlements::class)->subscription($this->partnerId());
    }

    /**
     * Quota with a percentage for the bar.
     *
     * @return array<string, mixed>
     */
    public function usage(): array
    {
        $quota = app(PlanEntitlements::class)->quota($this->partnerId());

        $quota['percent'] = $quota['allowance'] > 0
            ? min(100, round($quota['used'] / $quota['allowance'] * 100, 1))
            : 0.0;

        return $quota;
    }

    /**
     * What this plan does and doesn't include, as plain rows.
     *
     * @return array<int, array{label: string, included: bool}>
     */
    public function features(): array
    {
        $plan = $this->plan();

        return [
            // Always true, on every tier, and worth saying out loud: partners ask
            // whether a billing problem can cost their customers tickets.
            ['label' => 'Ticket delivery, confirmations and OTPs', 'included' => true],
            ['label' => 'Inbound auto-replies', 'included' => $plan->includes(PartnerPlan::FEATURE_INBOUND)],
            ['label' => 'Reminders and review requests', 'included' => $plan->includes(PartnerPlan::FEATURE_JOURNEYS)],
            ['label' => 'Instagram DMs (coming soon)', 'included' => $plan->includes(PartnerPlan::FEATURE_INSTAGRAM)],
        ];
    }

    public function getHeader(): ?View
    {
        return view('filament.pages.partner.plan-header');
    }

    // ---- top-ups -------------------------------------------------------------

    /** @return \Illuminate\Support\Collection<int, CreditPack> */
    public function packs(): \Illuminate\Support\Collection
    {
        return CreditPack::query()->where('is_active', true)->orderBy('sort')->get();
    }

    /**
     * Start a top-up: create the order, then hand the browser what Razorpay
     * checkout needs.
     */
    public function buyPack(int $packId): void
    {
        $pack = CreditPack::query()->where('is_active', true)->find($packId);

        if ($pack === null) {
            Notification::make()->title('That pack is no longer available.')->danger()->send();

            return;
        }

        try {
            $order = app(RazorpayBilling::class)->createCreditOrder(auth()->user(), $pack);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Could not start the payment')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        // Remember what this order was for, SERVER-side. Livewire properties are
        // client-writable, so trusting one here would let a partner pay for the
        // cheap pack and claim the big one.
        Cache::put(
            'rzp_credit_order:' . $order['order_id'],
            ['partner_id' => $this->partnerId(), 'pack_id' => $pack->id],
            now()->addMinutes(30),
        );

        $this->dispatch('open-razorpay', order: $order, name: $pack->name);
    }

    /**
     * Confirm a completed checkout. The signature proves Razorpay processed our
     * order; the cached record says what it was for.
     */
    public function confirmPack(string $orderId, string $paymentId, string $signature): void
    {
        if (! app(RazorpayGateway::class)->verifySignature($orderId, $paymentId, $signature)) {
            Notification::make()->title('Payment could not be verified')->danger()->send();

            return;
        }

        $pending = Cache::pull('rzp_credit_order:' . $orderId);

        if (! is_array($pending) || (int) $pending['partner_id'] !== $this->partnerId()) {
            Notification::make()->title('We could not match that payment to your account')->danger()->send();

            return;
        }

        $pack = CreditPack::find($pending['pack_id']);

        if ($pack === null) {
            return;
        }

        // The webhook may well have granted this already; both paths are keyed on
        // the payment id, so whichever arrives second does nothing.
        app(RazorpayBilling::class)->grantCredits(auth()->user(), $pack, $paymentId);

        Notification::make()
            ->title('Added ' . number_format($pack->conversations) . ' conversations')
            ->success()
            ->send();
    }
}
