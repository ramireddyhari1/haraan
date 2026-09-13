<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms;

use App\Filament\Resources\Hrms\Pages\ListWorkforceAuditLedgers;
use App\Models\Hrms\WorkforceAuditLedger;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WorkforceAuditLedgerResource extends Resource
{
    protected static ?string $model = WorkforceAuditLedger::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce & HR';

    protected static ?string $navigationLabel = 'Workforce Audit Ledger';

    protected static ?int $navigationSort = 15;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'control'
            && (auth()->user()?->isSuperAdmin() || auth()->user()?->hasRoleEither(['OPS', 'FINANCE', 'ADMIN']));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Timestamp (UTC)')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('event_name')
                    ->label('Event')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'APPROVED') => 'success',
                        str_contains($state, 'REJECTED') => 'danger',
                        str_contains($state, 'ESCALATED') => 'warning',
                        default => 'info',
                    })
                    ->searchable(),
                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->default('SYSTEM')
                    ->searchable(),
                TextColumn::make('actor_role')
                    ->label('Role')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('entity_type')
                    ->label('Target Entity')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->searchable(),
                TextColumn::make('entity_id')
                    ->label('Entity ID')
                    ->numeric(),
                TextColumn::make('client_ip')
                    ->label('IP Address')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('signature_hash')
                    ->label('HMAC Signature')
                    ->fontFamily('mono')
                    ->formatStateUsing(fn (string $state): string => substr($state, 0, 10) . '...' . substr($state, -6))
                    ->copyable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('event_name')
                    ->options([
                        'REGULARISATION_REQUESTED' => 'Regularisation Requested',
                        'REGULARISATION_APPROVED' => 'Regularisation Approved',
                        'REGULARISATION_REJECTED' => 'Regularisation Rejected',
                        'APPROVAL_TIER_1_CONFIRMED' => 'Tier 1 Confirmed',
                        'APPROVAL_TIER_2_CONFIRMED' => 'Tier 2 Confirmed',
                        'APPROVAL_SLA_BREACH_ESCALATED' => 'SLA Breach Escalated',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkforceAuditLedgers::route('/'),
        ];
    }
}
