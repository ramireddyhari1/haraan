<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use App\Models\Notification;
use App\Models\NotificationRead;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * The partner's bell inbox — the console twin of the app's notifications screen,
 * reading the same Notification rows (audience-matched via scopeForUser) and the
 * same NotificationRead ledger, so read state is shared across app and web.
 */
class PartnerNotifications extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell';

    protected static ?string $title = 'Notifications';

    protected static ?string $navigationLabel = 'Notifications';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.pages.partner.notifications';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'partner';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::unreadCountFor();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /** @return Collection<int, Notification> */
    public function getItemsProperty(): Collection
    {
        return Notification::query()
            ->sent()
            ->forUser(auth()->user())
            ->limit(50)
            ->get();
    }

    /** Ids this partner has already read. @return array<int, int> */
    public function getReadIdsProperty(): array
    {
        return NotificationRead::query()
            ->where('user_id', auth()->id())
            ->pluck('notification_id')
            ->all();
    }

    public function markAllRead(): void
    {
        $user = auth()->user();
        $ids = Notification::query()->sent()->forUser($user)->pluck('id')->all();

        $now = now();
        foreach ($ids as $id) {
            NotificationRead::query()->firstOrCreate(
                ['notification_id' => $id, 'user_id' => $user->id],
                ['read_at' => $now],
            );
        }

        FilamentNotification::make()
            ->title('All caught up')
            ->success()
            ->send();
    }

    private static function unreadCountFor(): int
    {
        $user = auth()->user();

        if ($user === null) {
            return 0;
        }

        $ids = Notification::query()->sent()->forUser($user)->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        $read = NotificationRead::query()
            ->where('user_id', $user->id)
            ->whereIn('notification_id', $ids)
            ->count();

        return max($ids->count() - $read, 0);
    }
}
