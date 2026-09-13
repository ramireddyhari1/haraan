<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms;

use App\Filament\Resources\Hrms\Pages\CreateHrmsAnnouncement;
use App\Filament\Resources\Hrms\Pages\EditHrmsAnnouncement;
use App\Filament\Resources\Hrms\Pages\ListHrmsAnnouncements;
use App\Models\Hrms\HrmsAnnouncement;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HrmsAnnouncementResource extends Resource
{
    protected static ?string $model = HrmsAnnouncement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce & HR';

    protected static ?string $navigationLabel = 'Announcements';

    protected static ?int $navigationSort = 12;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'control'
            && (auth()->user()?->isSuperAdmin() || auth()->user()?->hasRoleEither(['OPS', 'FINANCE']));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('title')->required(),
            Select::make('priority')
                ->options([
                    'normal' => 'Normal',
                    'high' => 'High',
                    'urgent' => 'Urgent',
                ])
                ->default('normal')
                ->required(),
            Select::make('audience')
                ->options([
                    'all' => 'All Staff & Workers',
                    'admin_hq' => 'HQ Staff Only',
                    'partner_staff' => 'Venue / Field Staff Only',
                ])
                ->default('all')
                ->required(),
            DateTimePicker::make('published_at')->default(now()),
            DateTimePicker::make('expires_at'),
            Toggle::make('is_active')->default(true),
            Textarea::make('body')->rows(4)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->weight('bold')->searchable(),
                TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('audience')->badge(),
                TextColumn::make('published_at')->dateTime('M d, Y H:i'),
                IconColumn::make('is_active')->boolean(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHrmsAnnouncements::route('/'),
            'create' => CreateHrmsAnnouncement::route('/create'),
            'edit' => EditHrmsAnnouncement::route('/{record}/edit'),
        ];
    }
}
