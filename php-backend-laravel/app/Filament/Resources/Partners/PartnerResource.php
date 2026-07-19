<?php

namespace App\Filament\Resources\Partners;

use App\Filament\Resources\Partners\Pages\CreatePartner;
use App\Filament\Resources\Partners\Pages\EditPartner;
use App\Filament\Resources\Partners\Pages\ListPartners;
use App\Filament\Support\AvatarColumn;
use App\Models\User;
use App\Support\ContactPrefill;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PartnerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'partner';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    /** Partners are users with the PARTNER role. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', 'PARTNER');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('phone'),
            Select::make('partner_type')
                ->options(['venue' => 'Venue owner', 'event' => 'Event organiser'])
                ->native(false),
            Select::make('status')
                ->options(['active' => 'Active', 'suspended' => 'Suspended'])
                ->default('active')
                ->native(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                AvatarColumn::make(
                    'avatar',
                    nameFor: fn (User $r): string => (string) ($r->name ?: 'Partner'),
                    avatarFor: fn (User $r): ?string => $r->avatar,
                ),
                TextColumn::make('name')
                    ->weight('bold')
                    ->description(fn (User $r): ?string => ContactPrefill::isRealEmail($r->email)
                        ? $r->email
                        : (trim((string) $r->phone) ?: null))
                    ->searchable(),
                TextColumn::make('phone')->placeholder('—')->toggleable(),
                TextColumn::make('partner_type')
                    ->label('Type')
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('partner_type')
                    ->options(['venue' => 'Venue owner', 'event' => 'Event organiser']),
                SelectFilter::make('status')
                    ->options(['active' => 'Active', 'suspended' => 'Suspended']),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartners::route('/'),
            'create' => CreatePartner::route('/create'),
            'edit' => EditPartner::route('/{record}/edit'),
        ];
    }
}
