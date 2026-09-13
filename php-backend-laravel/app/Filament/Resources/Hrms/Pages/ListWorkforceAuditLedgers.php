<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms\Pages;

use App\Filament\Resources\Hrms\WorkforceAuditLedgerResource;
use App\Services\Hrms\WorkforceAuditService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListWorkforceAuditLedgers extends ListRecords
{
    protected static string $resource = WorkforceAuditLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verifyIntegrity')
                ->label('Verify Hash Integrity')
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->action(function (WorkforceAuditService $auditService): void {
                    $result = $auditService->verifyChainIntegrity();

                    if ($result['is_valid']) {
                        Notification::make()
                            ->title('Audit Ledger Cryptographically Verified')
                            ->body($result['reason'])
                            ->success()
                            ->duration(8000)
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Tampering Detected!')
                            ->body($result['reason'])
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
