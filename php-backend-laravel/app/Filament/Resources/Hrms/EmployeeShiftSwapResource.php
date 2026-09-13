<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms;

use App\Filament\Resources\Hrms\Pages\ListEmployeeShiftSwaps;
use App\Models\Hrms\EmployeeShiftSwap;
use App\Services\Hrms\RosterService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class EmployeeShiftSwapResource extends Resource
{
    protected static ?string $model = EmployeeShiftSwap::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce & HR';

    protected static ?string $navigationLabel = 'Shift Swap Requests';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'control'
            && (auth()->user()?->isSuperAdmin() || auth()->user()?->hasRoleEither(['OPS', 'FINANCE']));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('requestorRoster.employee.full_name')
                    ->label('Requesting Employee')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('requestorRoster.shift.name')
                    ->label('Current Shift')
                    ->badge()
                    ->color('info'),
                TextColumn::make('requestorRoster.roster_date')
                    ->label('Shift Date')
                    ->date('M d, Y')
                    ->weight('bold'),
                TextColumn::make('targetEmployee.full_name')
                    ->label('Target Colleague')
                    ->searchable(),
                TextColumn::make('targetRoster.shift.name')
                    ->label('Exchange Shift')
                    ->placeholder('Cover / Transfer'),
                TextColumn::make('reason')
                    ->limit(28),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('reviewer.name')
                    ->label('Reviewed By')
                    ->placeholder('Pending'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->visible(fn (EmployeeShiftSwap $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (EmployeeShiftSwap $record): void {
                        try {
                            $service = app(RosterService::class);
                            $service->approveSwap($record, auth()->user());
                            Notification::make()->title('Shift Swap Approved')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
                \Filament\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-m-x-mark')
                    ->color('danger')
                    ->visible(fn (EmployeeShiftSwap $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (EmployeeShiftSwap $record): void {
                        try {
                            $service = app(RosterService::class);
                            $service->rejectSwap($record, auth()->user(), 'Rejected by Administrator');
                            Notification::make()->title('Shift Swap Rejected')->info()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeShiftSwaps::route('/'),
        ];
    }
}
