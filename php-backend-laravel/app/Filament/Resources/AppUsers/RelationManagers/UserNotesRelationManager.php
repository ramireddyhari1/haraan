<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppUsers\RelationManagers;

use App\Models\User;
use App\Models\UserNote;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Enterprise Secure Internal Admin Notes Relation Manager.
 *
 * Provides a confidential, auditable scratchpad for operators on a user's 360 profile.
 */
class UserNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'userNotes';

    protected static ?string $title = 'Internal Staff Notes';

    protected static string | \BackedEnum | null $icon = 'heroicon-o-document-text';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var User $ownerRecord */
        $count = $ownerRecord->userNotes()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function form(Schema $schema): Schema
    {
        $viewer = auth()->user();
        $canManageConfidential = $viewer !== null && ($viewer->isSuperAdmin() || $viewer->hasRoleEither(['OPS']));

        return $schema->components([
            Select::make('category')
                ->label('Note category')
                ->options(UserNote::CATEGORIES)
                ->default('general')
                ->required(),

            TextInput::make('title')
                ->label('Title / Summary')
                ->placeholder('e.g. Identity verification pending callback')
                ->maxLength(160),

            Textarea::make('content')
                ->label('Note details')
                ->placeholder('Enter internal observation, security warning, or support context…')
                ->required()
                ->rows(4)
                ->columnSpanFull(),

            Toggle::make('is_pinned')
                ->label('Pin note')
                ->helperText('Pinned notes display prominently at the top of the User 360 profile.')
                ->default(false),

            Toggle::make('is_confidential')
                ->label('Confidential note')
                ->helperText('Restricted: only Super-Admins and Operations can view or manage this note.')
                ->default(false)
                ->visible($canManageConfidential),
        ]);
    }

    public function table(Table $table): Table
    {
        $viewer = auth()->user();
        $canViewConfidential = $viewer !== null && ($viewer->isSuperAdmin() || $viewer->hasRoleEither(['OPS']));

        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(function (Builder $query) use ($canViewConfidential): Builder {
                $query->with(['author'])->orderBy('is_pinned', 'desc');

                return $canViewConfidential ? $query : $query->where('is_confidential', false);
            })
            ->columns([
                IconColumn::make('is_pinned')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-s-bookmark')
                    ->falseIcon('heroicon-o-bookmark')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => UserNote::CATEGORIES[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'risk'       => 'danger',
                        'financial'  => 'warning',
                        'moderation' => 'danger',
                        'support'    => 'info',
                        default      => 'primary',
                    }),

                TextColumn::make('title')
                    ->label('Summary')
                    ->weight('bold')
                    ->placeholder('—')
                    ->description(fn (UserNote $r): string => \Illuminate\Support\Str::limit($r->content, 90))
                    ->wrap()
                    ->searchable(),

                IconColumn::make('is_confidential')
                    ->label('Privacy')
                    ->boolean()
                    ->trueIcon('heroicon-s-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->tooltip(fn (UserNote $r): string => $r->is_confidential ? 'Confidential (Super-Admin & Ops only)' : 'Visible to internal staff'),

                TextColumn::make('author.name')
                    ->label('Author')
                    ->placeholder('System')
                    ->description(fn (UserNote $r): ?string => $r->author?->email),

                TextColumn::make('created_at')
                    ->label('Written')
                    ->since()
                    ->sortable()
                    ->tooltip(fn (UserNote $r): string => $r->created_at->format('d M Y, H:i')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add internal note')
                    ->icon('heroicon-m-plus'),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (UserNote $record): bool => auth()->id() === $record->author_id || (auth()->user()?->isSuperAdmin() ?? false)),

                DeleteAction::make()
                    ->visible(fn (UserNote $record): bool => auth()->id() === $record->author_id || (auth()->user()?->isSuperAdmin() ?? false)),
            ]);
    }
}
