<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms;

use App\Filament\Resources\Hrms\Pages\EditEmployeePayroll;
use App\Filament\Resources\Hrms\Pages\ListEmployeePayrolls;
use App\Models\Hrms\EmployeePayroll;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeePayrollResource extends Resource
{
    protected static ?string $model = EmployeePayroll::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce & HR';

    protected static ?string $navigationLabel = 'Payroll & Salaries';

    protected static ?int $navigationSort = 8;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'control'
            && (auth()->user()?->isSuperAdmin() || auth()->user()?->hasRoleEither(['OPS', 'FINANCE']));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payroll_month')->weight('bold')->sortable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(),
                TextColumn::make('employee.employee_code')->label('Emp Code')->fontFamily('mono'),
                TextColumn::make('gross_earnings')->money('INR')->sortable(),
                TextColumn::make('total_deductions')->money('INR')->color('danger'),
                TextColumn::make('net_salary')->money('INR')->weight('bold')->color('success')->sortable(),
                TextColumn::make('payslip_number')->fontFamily('mono')->label('Payslip Ref'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'approved' => 'info',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('payroll_month')
                    ->options([
                        now()->format('Y-m') => now()->format('F Y'),
                        now()->subMonth()->format('Y-m') => now()->subMonth()->format('F Y'),
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('print')
                    ->label('Payslip')
                    ->icon('heroicon-m-printer')
                    ->url(fn (EmployeePayroll $record): string => url("/payslips/{$record->id}/print"), shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeePayrolls::route('/'),
            'edit' => EditEmployeePayroll::route('/{record}/edit'),
        ];
    }
}
