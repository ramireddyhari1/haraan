<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use App\Models\PartnerPlan;
use App\Models\PartnerSubscription;
use App\Services\PlanEntitlements;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;

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
}
