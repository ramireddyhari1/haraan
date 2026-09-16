<?php

declare(strict_types=1);

namespace App\Filament\Resources\MemberSubscriptions;

use App\Models\AdminAction;
use App\Models\MemberPlan;
use App\Models\MemberSubscription;
use App\Models\User;
use App\Services\Membership\MembershipException;
use App\Services\Membership\MemberSubscriptions;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * The admin interventions on a member subscription, shared by the list rows and the view
 * page. Each one goes through MemberSubscriptions (so it's recorded on the timeline with the
 * admin as actor) and AdminAction::log.
 */
final class MemberSubscriptionActions
{
    /** @return list<Action> */
    public static function forRecord(): array
    {
        return [self::sync(), self::cancelAtPeriodEnd(), self::cancelNow(), self::extend(), self::revoke()];
    }

    public static function grant(): Action
    {
        return Action::make('grantPlan')
            ->label('Grant complimentary plan')
            ->icon('heroicon-m-gift')
            ->color('primary')
            ->modalDescription('Gives a member a plan without payment. It shows on their timeline with you as the actor.')
            ->schema([
                Select::make('user_id')
                    ->label('Member')
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => User::query()
                        ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('player_id', 'like', "%{$search}%"))
                        ->limit(25)
                        ->get()
                        ->mapWithKeys(fn (User $u): array => [$u->id => trim($u->name . ' · ' . ($u->email ?: $u->phone ?: $u->player_id))])
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name),
                Select::make('plan_id')
                    ->label('Plan')
                    ->required()
                    ->options(fn (): array => MemberPlan::query()->where('is_default', false)->orderBy('rank')->pluck('name', 'id')->all()),
                DateTimePicker::make('until')
                    ->label('Until')
                    ->minDate(now())
                    ->helperText('Leave empty for no end date.'),
                Textarea::make('note')
                    ->label('Why')
                    ->required()
                    ->rows(2)
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                $member = User::findOrFail($data['user_id']);
                $plan = MemberPlan::findOrFail($data['plan_id']);
                $until = filled($data['until'] ?? null) ? Carbon::parse($data['until']) : null;

                $subscription = app(MemberSubscriptions::class)->grant($member, $plan, $until, self::actor(), (string) $data['note']);
                AdminAction::log('member_subscription.granted', [
                    'subscription_id' => $subscription->id, 'user_id' => $member->id, 'plan' => $plan->code,
                    'until' => $until?->toIso8601String(),
                ]);

                Notification::make()->title("{$plan->name} granted to {$member->name}")->success()->send();
            });
    }

    private static function sync(): Action
    {
        return Action::make('syncRazorpay')
            ->label('Sync from Razorpay')
            ->icon('heroicon-m-arrow-path')
            ->color('gray')
            ->visible(fn (MemberSubscription $record): bool => $record->provider === MemberSubscription::PROVIDER_RAZORPAY
                && filled($record->provider_subscription_id))
            ->action(function (MemberSubscription $record): void {
                self::attempt(function () use ($record): string {
                    $outcome = app(MemberSubscriptions::class)->sync($record, self::actor());
                    AdminAction::log('member_subscription.synced', ['subscription_id' => $record->id, 'outcome' => $outcome]);

                    return 'Synced: ' . $outcome;
                });
            });
    }

    private static function cancelAtPeriodEnd(): Action
    {
        return Action::make('cancelAtPeriodEnd')
            ->label('Cancel at period end')
            ->icon('heroicon-m-calendar-days')
            ->color('warning')
            ->visible(fn (MemberSubscription $record): bool => $record->provider === MemberSubscription::PROVIDER_RAZORPAY
                && $record->status === MemberSubscription::STATUS_ACTIVE
                && ! $record->cancel_at_period_end)
            ->requiresConfirmation()
            ->modalDescription(fn (MemberSubscription $record): string => 'The member keeps their plan until '
                . ($record->current_period_end?->format('d M Y') ?? 'the period ends') . ' and is not charged again.')
            ->action(function (MemberSubscription $record): void {
                self::attempt(function () use ($record): string {
                    app(MemberSubscriptions::class)->cancelSubscription($record, true, self::actor());
                    AdminAction::log('member_subscription.cancel_scheduled', ['subscription_id' => $record->id]);

                    return 'Cancellation scheduled';
                });
            });
    }

    private static function cancelNow(): Action
    {
        return Action::make('cancelNow')
            ->label('Cancel immediately')
            ->icon('heroicon-m-x-circle')
            ->color('danger')
            ->visible(fn (MemberSubscription $record): bool => $record->provider === MemberSubscription::PROVIDER_RAZORPAY
                && ! $record->isTerminal()
                && $record->status !== MemberSubscription::STATUS_CREATED)
            ->requiresConfirmation()
            ->modalDescription('Ends access now and stops all future charges. No refund is issued by this action — refunds are made in the Razorpay dashboard.')
            ->action(function (MemberSubscription $record): void {
                self::attempt(function () use ($record): string {
                    app(MemberSubscriptions::class)->cancelSubscription($record, false, self::actor());
                    AdminAction::log('member_subscription.cancelled', ['subscription_id' => $record->id]);

                    return 'Subscription cancelled';
                });
            });
    }

    private static function extend(): Action
    {
        return Action::make('extendGrant')
            ->label('Change end date')
            ->icon('heroicon-m-clock')
            ->color('info')
            ->visible(fn (MemberSubscription $record): bool => $record->provider === MemberSubscription::PROVIDER_ADMIN
                && $record->status !== MemberSubscription::STATUS_REVOKED)
            ->schema([
                DateTimePicker::make('until')
                    ->label('Until')
                    ->minDate(now())
                    ->helperText('Leave empty for no end date.'),
            ])
            ->fillForm(fn (MemberSubscription $record): array => ['until' => $record->current_period_end])
            ->action(function (MemberSubscription $record, array $data): void {
                self::attempt(function () use ($record, $data): string {
                    $until = filled($data['until'] ?? null) ? Carbon::parse($data['until']) : null;
                    app(MemberSubscriptions::class)->extendGrant($record, $until, self::actor());
                    AdminAction::log('member_subscription.extended', ['subscription_id' => $record->id, 'until' => $until?->toIso8601String()]);

                    return 'End date updated';
                });
            });
    }

    private static function revoke(): Action
    {
        return Action::make('revokeGrant')
            ->label('Revoke')
            ->icon('heroicon-m-no-symbol')
            ->color('danger')
            ->visible(fn (MemberSubscription $record): bool => $record->provider === MemberSubscription::PROVIDER_ADMIN
                && $record->status === MemberSubscription::STATUS_ACTIVE)
            ->schema([
                Textarea::make('reason')->required()->rows(2)->maxLength(255),
            ])
            ->action(function (MemberSubscription $record, array $data): void {
                self::attempt(function () use ($record, $data): string {
                    app(MemberSubscriptions::class)->revokeGrant($record, self::actor(), (string) $data['reason']);
                    AdminAction::log('member_subscription.revoked', ['subscription_id' => $record->id]);

                    return 'Plan revoked';
                });
            });
    }

    /** @param callable(): string $work */
    private static function attempt(callable $work): void
    {
        try {
            Notification::make()->title($work())->success()->send();
        } catch (MembershipException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    private static function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
