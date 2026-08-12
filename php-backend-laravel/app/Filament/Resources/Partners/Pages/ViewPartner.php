<?php

declare(strict_types=1);

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Resources\Partners\PartnerResource;
use App\Filament\Resources\Partners\Widgets\PartnerOverviewStatsWidget;
use App\Filament\Resources\Partners\Widgets\PartnerRevenueChartWidget;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * Partner overview — the read-first profile that opens when an admin clicks a
 * partner's name. Shows who they are, what they run (events / venues via the
 * resource relation managers), and what they earn (header KPI + revenue chart).
 * "Edit" is demoted to a header action so the page leads with information.
 */
class ViewPartner extends ViewRecord
{
    protected static string $resource = PartnerResource::class;

    public function getTitle(): string
    {
        return (string) ($this->getRecord()->name ?: 'Partner');
    }

    /** KPI strip + earnings chart sit above the identity infolist. */
    protected function getHeaderWidgets(): array
    {
        return [
            PartnerOverviewStatsWidget::class,
            PartnerRevenueChartWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('toggleStatus')
                ->label(fn (): string => $this->isActive() ? 'Suspend' : 'Activate')
                ->icon(fn (): string => $this->isActive() ? 'heroicon-m-no-symbol' : 'heroicon-m-check-circle')
                ->color(fn (): string => $this->isActive() ? 'danger' : 'success')
                ->requiresConfirmation()
                ->modalDescription(fn (): string => $this->isActive()
                    ? 'Suspend this partner? They will lose access to the partner console until reactivated.'
                    : 'Reactivate this partner and restore their console access?')
                ->action(function (): void {
                    $record = $this->getRecord();
                    $record->status = $this->isActive() ? 'suspended' : 'active';
                    $record->save();

                    Notification::make()
                        ->title($this->isActive() ? 'Partner reactivated' : 'Partner suspended')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function isActive(): bool
    {
        $record = $this->getRecord();

        return $record instanceof User
            && strtolower((string) $record->status) === 'active';
    }
}
