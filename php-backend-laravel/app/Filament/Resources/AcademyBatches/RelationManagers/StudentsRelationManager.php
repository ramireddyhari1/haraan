<?php

declare(strict_types=1);

namespace App\Filament\Resources\AcademyBatches\RelationManagers;

use App\Models\AdminAction;
use App\Models\BatchEnrollment;
use App\Support\BusinessClock;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The batch roster. The admin can take a student off (or put them back) with a
 * reason; their fees and attendance stay either way. Fee dates are not edited
 * here: cover only moves when a fee row is collected.
 */
class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'Students';

    public function table(Table $table): Table
    {
        $today = BusinessClock::todayDate();

        return $table
            ->recordTitleAttribute('student_name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('attendance'))
            ->defaultSort('student_name')
            ->columns([
                TextColumn::make('student_name')->label('Student')->weight('medium')->searchable()
                    ->description(fn (BatchEnrollment $record): string => '+91 ' . $record->student_phone),
                TextColumn::make('paid_until')
                    ->label('Fees')
                    ->state(fn (BatchEnrollment $record): string => match (true) {
                        $record->paid_until === null => 'Nothing on record',
                        $record->paid_until->lt($today) => 'Overdue since ' . $record->paid_until->format('d M'),
                        default => 'Paid till ' . $record->paid_until->format('d M Y'),
                    })
                    ->color(fn (BatchEnrollment $record): string => match (true) {
                        $record->paid_until === null => 'gray',
                        $record->paid_until->lt($today) => 'danger',
                        $record->paid_until->lte($today->copy()->addDays(5)) => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('amount_paid')->label('Paid in total')->money('INR'),
                TextColumn::make('attendance_count')->label('Classes attended'),
                TextColumn::make('is_active')
                    ->label('On batch')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'Removed')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('On batch')->default(true),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('toggleSeat')
                    ->label(fn (BatchEnrollment $record): string => $record->is_active ? 'Remove' : 'Put back')
                    ->color(fn (BatchEnrollment $record): string => $record->is_active ? 'danger' : 'success')
                    ->icon(fn (BatchEnrollment $record): string => $record->is_active ? 'heroicon-o-user-minus' : 'heroicon-o-user-plus')
                    ->modalHeading(fn (BatchEnrollment $record): string => ($record->is_active ? 'Remove ' : 'Put back ') . $record->student_name)
                    ->modalDescription('Fees and attendance stay on record either way.')
                    ->schema([
                        Textarea::make('reason')->label('Reason (kept in the audit log)')->required()->maxLength(500),
                    ])
                    ->action(function (BatchEnrollment $record, array $data): void {
                        $record->forceFill(['is_active' => ! $record->is_active])->save();
                        AdminAction::log($record->is_active ? 'academy_student.restored' : 'academy_student.removed', [
                            'batch_id' => $record->venue_batch_id,
                            'student' => $record->student_name,
                            'reason' => $data['reason'],
                        ], $record);
                        Notification::make()->title($record->is_active ? 'Student put back' : 'Student removed')->success()->send();
                    }),
            ]);
    }
}
