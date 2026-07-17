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
use Filament\Schemas\Components\Utilities\Get;
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
                ->live()
                ->options(Notification::AUDIENCE_TYPES)
                ->helperText(fn (Get $get): string => self::reachHint((string) $get('audience_type'))),
            TextInput::make('audience_value')
                ->label('Audience value')
                ->maxLength(120)
                // Only the static segments need a typed value; activity segments and
                // "Everyone" resolve themselves, so hide the box to avoid confusion.
                ->visible(fn (Get $get): bool => in_array($get('audience_type'), ['district', 'state', 'sport', 'user'], true))
                ->required(fn (Get $get): bool => in_array($get('audience_type'), ['district', 'state', 'sport', 'user'], true))
                ->helperText('The exact district or state name, the sport (Cricket / Football / Badminton / Basketball), or the target user id.'),
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

    /**
     * A plain-English "who this reaches" line under the segment picker. For activity
     * segments it runs the same query used at send time, so the operator sees the real
     * headcount before sending (a snapshot — it's re-frozen at the moment of sending).
     */
    private static function reachHint(string $type): string
    {
        if ($type === 'all') {
            return 'Everyone: ' . \App\Models\User::count() . ' users.';
        }

        if (in_array($type, Notification::ACTIVITY_AUDIENCE_TYPES, true)) {
            $notification = new Notification(['audience_type' => $type]);
            $count = $notification->activityAudienceQuery()->count();

            return "Reaches about {$count} users right now (frozen when you send).";
        }

        return 'Who receives this.';
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
                TextColumn::make('reach')
                    ->label('Reach')
                    ->state(fn (Notification $r): string => $r->status === 'sent' ? (string) $r->reach() : '—')
                    ->alignEnd()
                    ->tooltip('Users this was aimed at (exact for activity segments; current match for others).'),
                TextColumn::make('reads_count')
                    ->label('Opened')
                    ->counts('reads')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('open_rate')
                    ->label('Open rate')
                    ->badge()
                    ->alignEnd()
                    ->state(function (Notification $r): string {
                        if ($r->status !== 'sent') {
                            return '—';
                        }
                        $rate = $r->openRate();

                        return $rate === null ? '—' : $rate . '%';
                    })
                    ->color(function (Notification $r): string {
                        $rate = $r->status === 'sent' ? $r->openRate() : null;

                        return match (true) {
                            $rate === null => 'gray',
                            $rate >= 40    => 'success',
                            $rate >= 15    => 'warning',
                            default        => 'danger',
                        };
                    }),
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
