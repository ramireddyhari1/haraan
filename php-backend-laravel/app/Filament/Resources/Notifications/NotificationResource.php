<?php

declare(strict_types=1);

namespace App\Filament\Resources\Notifications;

use App\Filament\Resources\Notifications\Pages\CreateNotification;
use App\Filament\Resources\Notifications\Pages\EditNotification;
use App\Filament\Resources\Notifications\Pages\ListNotifications;
use App\Models\Notification;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Compose and send bell-inbox notifications to the app. A "Sent" notification
 * appears in the targeted users' bells immediately (open apps via Reverb);
 * "Draft" is saved but not delivered.
 */
class NotificationResource extends Resource
{
    protected static ?string $model = Notification::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Notifications';

    protected static ?string $modelLabel = 'notification';

    protected static ?int $navigationSort = 22;

    protected static ?string $recordTitleAttribute = 'title';

    /** Super-admins and ops/marketing — the people who message the userbase. */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->isSuperAdmin() || $user->hasRoleEither(['OPS', 'MARKETING']));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->required()
                ->maxLength(120)
                ->helperText('The bold headline shown in the bell (keep it short).'),
            Textarea::make('body')
                ->required()
                ->rows(4)
                ->maxLength(2000)
                ->helperText('The message body.'),
            TextInput::make('image_url')
                ->label('Image URL (optional)')
                ->url()
                ->maxLength(500)
                ->helperText('An optional banner image shown with the message.'),
            TextInput::make('deep_link')
                ->label('Tap target (optional)')
                ->maxLength(255)
                ->helperText('Where tapping opens: e.g. event:12, match:35, venue:3, or a full https:// URL. Leave blank for none.'),
            Select::make('audience_type')
                ->label('Send to')
                ->required()
                ->default('all')
                ->options(Notification::AUDIENCE_TYPES)
                ->helperText('Who receives this.'),
            TextInput::make('audience_value')
                ->label('Audience value')
                ->maxLength(120)
                ->helperText('Leave blank for "Everyone". Otherwise the exact district or state name, the sport (Cricket / Football / Badminton / Basketball), or the target user id.'),
            Select::make('status')
                ->required()
                ->default('draft')
                ->options([
                    'draft' => 'Draft (not delivered)',
                    'sent'  => 'Sent (deliver now)',
                ])
                ->helperText('Switch to "Sent" to push it into users\' bells immediately.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Notification $r): string => \Illuminate\Support\Str::limit($r->body, 60)),
                TextColumn::make('audience_type')
                    ->label('Audience')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (Notification $r): string => (Notification::AUDIENCE_TYPES[$r->audience_type] ?? $r->audience_type)
                        . ($r->audience_value ? " · {$r->audience_value}" : '')),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'sent' ? 'success' : 'gray'),
                TextColumn::make('reads_count')
                    ->label('Reads')
                    ->counts('reads')
                    ->sortable(),
                TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime('M j, g:i A')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListNotifications::route('/'),
            'create' => CreateNotification::route('/create'),
            'edit'   => EditNotification::route('/{record}/edit'),
        ];
    }
}
