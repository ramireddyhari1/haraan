<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms;

use App\Filament\Resources\Hrms\Pages\EditEmployeeLeave;
use App\Filament\Resources\Hrms\Pages\ListEmployeeLeaves;
use App\Models\Hrms\EmployeeLeaveRequest;
use App\Services\Hrms\LeaveService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class EmployeeLeaveResource extends Resource
{
    protected static ?string $model = EmployeeLeaveRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-right-start-on-rectangle';

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce & HR';

    protected static ?string $navigationLabel = 'Leave Approvals';

    protected static ?int $navigationSort = 7;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'control'
            && (auth()->user()?->isSuperAdmin() || auth()->user()?->hasRoleEither(['OPS', 'FINANCE']));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.full_name')->label('Employee')->searchable()->weight('bold'),
                TextColumn::make('leaveType.name')->label('Category')->badge(),
                TextColumn::make('start_date')->date('M d, Y'),
                TextColumn::make('end_date')->date('M d, Y'),
                TextColumn::make('total_days')->suffix('d')->weight('bold'),
                TextColumn::make('reason')->limit(30),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('approver.name')->label('Reviewed By')->placeholder('Pending'),
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
                    ->visible(fn (EmployeeLeaveRequest $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (EmployeeLeaveRequest $record): void {
                        try {
                            $service = app(LeaveService::class);
                            $service->approve($record, auth()->user(), 'Approved by Admin');
                            Notification::make()->title('Leave Request Approved')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
                \Filament\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-m-x-mark')
                    ->color('danger')
                    ->visible(fn (EmployeeLeaveRequest $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (EmployeeLeaveRequest $record): void {
                        try {
                            $service = app(LeaveService::class);
                            $service->reject($record, auth()->user(), 'Rejected by Admin');
                            Notification::make()->title('Leave Request Rejected')->info()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeLeaves::route('/'),
            'edit' => EditEmployeeLeave::route('/{record}/edit'),
        ];
    }
}
