<?php

namespace App\Filament\Resources\SupportThreads\Tables;

use App\Models\SupportThread;
use App\Services\Membership\MemberEntitlements;
use App\Support\Membership\MemberFeature;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupportThreadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager-load the person + assignee + topic the row renders, so the
            // list isn't N+1 once the avatar reads user->avatar too.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'assignee', 'category']))
            ->extraAttributes(['class' => 'stx-table'])
            // Rows waiting on a reply carry an accent rule so they read first.
            ->recordClasses(fn (SupportThread $r): ?string => $r->admin_unread_count > 0 ? 'stx-row--unread' : null)
            ->columns([
                // One cell for the person: photo, unread count, name, plan, subject.
                // Priority-support members are resolved by the member entitlement
                // engine, so /control and the app can never disagree.
                ViewColumn::make('user.name')
                    ->label('Conversation')
                    ->view('filament.resources.support-threads.conversation-cell')
                    ->viewData([
                        'getPlan' => fn (SupportThread $r): ?string => $r->user !== null
                            && app(MemberEntitlements::class)->allows($r->user, MemberFeature::SUPPORT_PRIORITY)
                                ? app(MemberEntitlements::class)->for($r->user)->plan->name
                                : null,
                    ])
                    ->searchable(),
                TextColumn::make('category.label')
                    ->label('Topic')
                    ->extraAttributes(['class' => 'stx-topic'])
                    ->placeholder('Unsorted'),
                ViewColumn::make('status')
                    ->view('filament.resources.support-threads.status-cell'),
                ViewColumn::make('assignee.name')
                    ->label('Owner')
                    ->view('filament.resources.support-threads.owner-cell')
                    ->searchable(),
                TextColumn::make('last_message_at')
                    ->label('Last activity')
                    ->since()
                    ->dateTimeTooltip()
                    ->color('gray')
                    ->sortable(),
            ])
            // Waiting-on-us first, then most recent activity.
            ->defaultSort('last_message_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open'    => 'Open',
                        'pending' => 'Pending',
                        'closed'  => 'Closed',
                    ]),
                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('category_id')
                    ->label('Topic')
                    ->relationship('category', 'label')
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Open')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->button()
                    ->outlined()
                    ->size('sm')
                    ->color('primary'),

                ActionGroup::make([

                // Take ownership without opening the thread. Hidden once it's yours.
                Action::make('assignToMe')
                    ->label('Assign to me')
                    ->icon('heroicon-m-user-plus')
                    ->color('info')
                    ->visible(fn (SupportThread $record): bool => $record->assigned_to !== auth()->id())
                    ->action(function (SupportThread $record): void {
                        $record->forceFill(['assigned_to' => auth()->id()])->save();
                        Notification::make()->title('Assigned to you')->success()->send();
                    }),

                // Mark resolved. A new user message reopens it automatically.
                Action::make('close')
                    ->label('Close')
                    ->icon('heroicon-m-check-circle')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (SupportThread $record): bool => $record->status !== 'closed')
                    ->action(function (SupportThread $record): void {
                        $record->forceFill(['status' => 'closed'])->save();
                        Notification::make()->title('Conversation closed')->success()->send();
                    }),

                Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-m-arrow-path')
                    ->color('warning')
                    ->visible(fn (SupportThread $record): bool => $record->status === 'closed')
                    ->action(function (SupportThread $record): void {
                        $record->forceFill(['status' => 'open'])->save();
                        Notification::make()->title('Conversation reopened')->success()->send();
                    }),
                ])
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->color('gray')
                    ->tooltip('More'),
            ]);
    }
}
