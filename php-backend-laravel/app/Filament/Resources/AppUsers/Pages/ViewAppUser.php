<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppUsers\Pages;

use App\Filament\Actions\ResetPasswordAction;
use App\Filament\Resources\AppUsers\AppUserResource;
use App\Filament\Resources\AppUsers\Schemas\UserInfolist;
use App\Filament\Resources\AppUsers\Widgets\UserOverviewStatsWidget;
use App\Filament\Resources\AppUsers\Widgets\UserRiskTrustWidget;
use App\Filament\Resources\AppUsers\Widgets\UserSpendChartWidget;
use App\Models\AdminAction;
use App\Models\User;
use App\Support\JwtService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * Enterprise User 360 overview page — the single pane of glass for an app user.
 * Leads with telemetry, identity, sports profile, financial history, and relation tabs.
 */
class ViewAppUser extends ViewRecord
{
    protected static string $resource = AppUserResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $operator = auth()->user();
        $target = $this->getRecord();

        // Audit unmasked PII views by privileged operators
        if ($operator && $target && $operator->id !== $target->id && UserInfolist::canViewPii($target)) {
            AdminAction::log('user.pii_viewed', [
                'channel' => 'view_app_user_page',
                'accessed_fields' => ['email', 'phone', 'date_of_birth'],
            ], $target);
        }
    }

    public function getTitle(): string
    {
        $record = $this->getRecord();
        $name = (string) ($record->name ?: 'User');
        $memberId = (string) ($record->player_id ?: User::memberId($record->id));

        return "{$name} · {$memberId}";
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UserOverviewStatsWidget::class,
            UserRiskTrustWidget::class,
            UserSpendChartWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        /** @var User $record */
        $record = $this->getRecord();

        return [
            EditAction::make(),

            Action::make('recalculateKpis')
                ->label('Refresh')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    /** @var User $record */
                    $record = $this->getRecord();
                    $record->recalculateKpiMetrics();

                    Notification::make()
                        ->title('Refreshed')
                        ->body('Lifetime spend, bookings, and activity metrics have been synchronized.')
                        ->success()
                        ->send();
                }),

            Action::make('toggleStatus')
                ->label(fn (): string => $this->isActive() ? 'Suspend account' : 'Reactivate account')
                ->icon(fn (): string => $this->isActive() ? 'heroicon-m-no-symbol' : 'heroicon-m-check-circle')
                ->color(fn (): string => $this->isActive() ? 'danger' : 'success')
                ->requiresConfirmation()
                ->modalHeading(fn (): string => $this->isActive() ? 'Suspend user account?' : 'Reactivate user account?')
                ->modalDescription(fn (): string => $this->isActive()
                    ? 'Suspend this user? Their active sessions and JWT tokens will be revoked immediately and they will be barred from login.'
                    : 'Reactivate this user and restore access to the mobile app and website?')
                ->action(function (): void {
                    $target = $this->getRecord();
                    $suspending = $this->isActive();
                    $newStatus = $suspending ? 'SUSPENDED' : 'ACTIVE';

                    $target->status = $newStatus;
                    $target->save();

                    AdminAction::log(
                        $suspending ? 'user.suspended' : 'user.reactivated',
                        ['status' => $newStatus, 'token_version' => $target->token_version],
                        $target
                    );

                    Notification::make()
                        ->title($suspending ? 'User account suspended & sessions revoked' : 'User account reactivated')
                        ->success()
                        ->send();
                }),

            Action::make('toggleVerification')
                ->label(fn (): string => (bool) $this->getRecord()->is_verified ? 'Remove blue tick' : 'Verify player (Blue tick)')
                ->icon('heroicon-m-check-badge')
                ->color(fn (): string => (bool) $this->getRecord()->is_verified ? 'gray' : 'primary')
                ->requiresConfirmation()
                ->modalHeading(fn (): string => (bool) $this->getRecord()->is_verified ? 'Remove verified blue tick?' : 'Grant verified blue tick badge?')
                ->modalDescription(fn (): string => (bool) $this->getRecord()->is_verified
                    ? 'Remove the verified badge from this player profile?'
                    : 'Grant this player account a verified blue tick across the mobile app and leaderboards?')
                ->action(function (): void {
                    $record = $this->getRecord();
                    $record->is_verified = ! $record->is_verified;
                    $record->verified_at = $record->is_verified ? now() : null;
                    $record->save();

                    AdminAction::log(
                        $record->is_verified ? 'user.verified' : 'user.unverified',
                        ['is_verified' => $record->is_verified],
                        $record
                    );

                    Notification::make()
                        ->title($record->is_verified ? 'Player verified with blue tick' : 'Verification badge removed')
                        ->success()
                        ->send();
                }),

            ResetPasswordAction::make(),

            Action::make('revokeSessions')
                ->label('Revoke all sessions')
                ->icon('heroicon-m-arrow-path')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Revoke all active sessions?')
                ->modalDescription('This will immediately invalidate every active JWT token across Android, iOS, and Web. The user will be signed out on all devices.')
                ->modalSubmitActionLabel('Revoke all now')
                ->action(function (): void {
                    $record = $this->getRecord();
                    $newVersion = JwtService::revokeAllFor($record);

                    AdminAction::log('user.sessions_revoked', ['token_version' => $newVersion], $record);

                    Notification::make()
                        ->title('All active sessions revoked')
                        ->body("Token version moved to v{$newVersion}. User signed out on all devices.")
                        ->success()
                        ->send();
                }),

            ActionGroup::make([
                Action::make('jumpBookings')
                    ->label('Filter in Bookings desk')
                    ->icon('heroicon-m-calendar-days')
                    ->url(fn (): string => route('filament.control.events.resources.bookings.index', [
                        'tableFilters' => ['user_id' => ['value' => $this->getRecord()->id]],
                    ]))
                    ->openUrlInNewTab(),

                Action::make('jumpSupport')
                    ->label('Filter in Support threads')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->url(fn (): string => route('filament.control.resources.support-threads.index', [
                        'tableFilters' => ['user_id' => ['value' => $this->getRecord()->id]],
                    ]))
                    ->openUrlInNewTab(),

                Action::make('jumpMatches')
                    ->label('Filter in Live Matches')
                    ->icon('heroicon-m-trophy')
                    ->url(fn (): string => route('filament.control.game-hub.resources.live-matches.index', [
                        'tableFilters' => ['user_id' => ['value' => $this->getRecord()->id]],
                    ]))
                    ->openUrlInNewTab(),

                Action::make('jumpSubscriptions')
                    ->label('Filter in Subscriptions')
                    ->icon('heroicon-m-sparkles')
                    ->url(fn (): string => route('filament.control.finance.resources.member-subscriptions.index', [
                        'tableFilters' => ['user_id' => ['value' => $this->getRecord()->id]],
                    ]))
                    ->openUrlInNewTab(),

                Action::make('jumpRewards')
                    ->label('Filter in Rewards ledger')
                    ->icon('heroicon-m-gift')
                    ->url(fn (): string => route('filament.control.resources.rewards.reward-grants.index', [
                        'tableFilters' => ['user_id' => ['value' => $this->getRecord()->id]],
                    ]))
                    ->openUrlInNewTab(),

                Action::make('jumpModeration')
                    ->label('Filter in Player Reports')
                    ->icon('heroicon-m-flag')
                    ->url(fn (): string => route('filament.control.resources.player-reports.index', [
                        'tableFilters' => ['reported_id' => ['value' => $this->getRecord()->id]],
                    ]))
                    ->openUrlInNewTab(),
            ])
                ->label('Cross-resource shortcuts')
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->color('gray'),
        ];
    }

    private function isActive(): bool
    {
        $record = $this->getRecord();

        return $record instanceof User && $record->isAccountActive();
    }
}
