<?php

namespace App\Filament\Resources\EmailSenders\Tables;

use App\Models\EmailSender;
use App\Services\EmailOtpService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmailSendersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('username')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                IconColumn::make('healthy')
                    ->label('Health')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('usage')
                    ->label('Used today')
                    ->state(fn (EmailSender $r): string => ($r->sent_date?->isToday() ? $r->sent_today : 0) . ' / ' . $r->daily_limit)
                    ->badge()
                    ->color(fn (EmailSender $r): string => $r->remainingToday() > 0 ? 'gray' : 'danger'),

                TextColumn::make('last_used_at')
                    ->label('Last used')
                    ->since()
                    ->placeholder('never')
                    ->sortable(),

                TextColumn::make('last_error')
                    ->label('Last error')
                    ->limit(30)
                    ->tooltip(fn (EmailSender $r): ?string => $r->last_error)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('sendTest')
                    ->label('Send test')
                    ->icon('heroicon-o-paper-airplane')
                    ->schema([
                        TextInput::make('to')
                            ->label('Send test email to')
                            ->email()
                            ->required()
                            ->default(fn (): ?string => auth()->user()?->email),
                    ])
                    ->action(function (EmailSender $record, array $data): void {
                        $ok = app(EmailOtpService::class)->sendTest($record, $data['to']);
                        if ($ok) {
                            Notification::make()
                                ->title('Test email sent')
                                ->body("Sent from {$record->username} to {$data['to']}.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Test email failed')
                                ->body($record->fresh()?->last_error ?? 'Check the credentials and try again.')
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
