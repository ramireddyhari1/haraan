<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Booking;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;

/**
 * One source of truth for how a booking is presented in a Filament table.
 *
 * The status → colour/label mapping used to be copy-pasted into every bookings
 * table and widget; a new status (or a colour tweak) meant editing a dozen
 * files and hoping none drifted. These factories keep the pill — and the
 * customer avatar — identical everywhere they appear.
 */
final class BookingTablePresenter
{
    /** Statuses that mean money was collected. */
    private const PAID = ['confirmed', 'paid', 'completed', 'checked_in'];

    private const WARN = ['pending', 'reserved'];

    private const BAD = ['cancelled', 'canceled', 'failed', 'refunded'];

    /** Filament semantic colour for a (mixed-case) booking status. */
    public static function statusColor(?string $status): string
    {
        $s = strtolower((string) $status);

        return match (true) {
            in_array($s, self::PAID, true) => 'success',
            in_array($s, self::WARN, true) => 'warning',
            in_array($s, self::BAD, true) => 'danger',
            default => 'gray',
        };
    }

    /** Human label: "checked_in" → "Checked in", else Titlecased. */
    public static function statusLabel(?string $status): string
    {
        return ucfirst(str_replace('_', ' ', strtolower((string) $status)));
    }

    /** A small leading glyph that reinforces the colour at a glance. */
    public static function statusIcon(?string $status): ?string
    {
        return match (self::statusColor($status)) {
            'success' => 'heroicon-m-check-circle',
            'warning' => 'heroicon-m-clock',
            'danger' => 'heroicon-m-x-circle',
            default => null,
        };
    }

    /** The standardized status pill column, ready to drop into any bookings table. */
    public static function statusColumn(string $name = 'status'): TextColumn
    {
        return TextColumn::make($name)
            ->badge()
            ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
            ->icon(fn (?string $state): ?string => self::statusIcon($state))
            ->color(fn (?string $state): string => self::statusColor($state));
    }

    /** A circular customer avatar column — the account photo, or generated initials. */
    public static function customerAvatarColumn(): ImageColumn
    {
        return AvatarColumn::make(
            'customer_avatar',
            nameFor: fn (Booking $r): string => (string) ($r->user?->name ?? $r->guest_name ?? 'Guest'),
            avatarFor: fn (Booking $r): ?string => $r->user?->avatar,
        );
    }
}
