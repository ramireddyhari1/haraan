<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards\RelationManagers;

use App\Models\AdminAction;
use App\Models\RewardCode;
use App\Models\RewardCodePool;
use App\Services\Rewards\CodePoolImporter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * The codes in a pool — always masked. Import adds codes in bulk (audited by count). Reveal
 * shows one code in full to a super-admin, for a support case, and is audited by id; the code
 * itself never enters the log.
 */
class RewardCodesRelationManager extends RelationManager
{
    protected static string $relationship = 'codes';

    protected static ?string $title = 'Codes';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('masked')->label('Code')->state(fn (RewardCode $record): string => $record->masked()),
                TextColumn::make('grant_id')->label('Given to reward')->placeholder('Available'),
                TextColumn::make('assigned_at')->label('Given')->dateTime('d M Y H:i')->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('given')->label('Given out')
                    ->queries(true: fn ($q) => $q->whereNotNull('grant_id'), false: fn ($q) => $q->whereNull('grant_id')),
            ])
            ->headerActions([
                Action::make('import')->label('Import codes')->icon('heroicon-m-arrow-up-tray')
                    ->schema([
                        Textarea::make('codes')->label('Codes, one per line (a CSV’s first column works)')
                            ->rows(12)->required(),
                    ])
                    ->action(function (array $data): void {
                        /** @var RewardCodePool $pool */
                        $pool = $this->getOwnerRecord();
                        $r = app(CodePoolImporter::class)->import($pool, (string) $data['codes']);
                        Notification::make()
                            ->title("Added {$r['added']} code(s)")
                            ->body("Skipped {$r['duplicates']} duplicate(s) and {$r['invalid']} invalid line(s).")
                            ->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('reveal')->label('Reveal')->icon('heroicon-m-eye')->color('gray')
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin())
                    ->requiresConfirmation()
                    ->modalDescription('Shows this code in full. The reveal is recorded in the audit log.')
                    ->action(function (RewardCode $record): void {
                        abort_unless((bool) auth()->user()?->isSuperAdmin(), 403);
                        AdminAction::log('reward_code.revealed', ['code_id' => $record->id, 'pool_id' => $record->pool_id, 'grant_id' => $record->grant_id]);
                        Notification::make()->title('Code')->body((string) $record->code)->persistent()->send();
                    }),
                DeleteAction::make()
                    ->visible(fn (RewardCode $record): bool => $record->grant_id === null)
                    ->after(fn (RewardCode $record) => AdminAction::log('reward_code.deleted', ['code_id' => $record->id, 'pool_id' => $record->pool_id])),
            ]);
    }
}
