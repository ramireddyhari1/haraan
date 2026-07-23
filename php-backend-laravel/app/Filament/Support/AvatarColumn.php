<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\MediaUrl;
use Closure;
use Filament\Tables\Columns\ImageColumn;

/**
 * A reusable circular avatar column for any Filament table.
 *
 * Resolves an uploaded photo (via the shared MediaUrl resolver); when there
 * isn't one it draws a self-contained data-URI initials chip, coloured
 * deterministically from the name — no external avatar service, works offline.
 * Callers pass resolvers so the same column serves users, bookings, partners…
 */
final class AvatarColumn
{
    /**
     * @param  string   $key       column key (arbitrary; state is supplied by the resolvers)
     * @param  Closure  $nameFor   fn($record): ?string — the display name (drives initials + hue)
     * @param  Closure  $avatarFor fn($record): ?string — stored avatar path/URL, or null
     */
    public static function make(string $key, Closure $nameFor, Closure $avatarFor, int $size = 36): ImageColumn
    {
        return ImageColumn::make($key)
            ->label('')
            ->circular()
            ->size($size)
            ->getStateUsing(function ($record) use ($nameFor, $avatarFor): string {
                $resolved = MediaUrl::resolve($avatarFor($record));

                return ($resolved !== null && $resolved !== '')
                    ? $resolved
                    : self::initials((string) ($nameFor($record) ?? ''));
            })
            ->extraImgAttributes(['loading' => 'lazy']);
    }

    /** A self-contained data-URI avatar: initials on a colour derived from the name. */
    public static function initials(string $name): string
    {
        $name = trim($name) !== '' ? trim($name) : 'Guest';
        $parts = preg_split('/\s+/', $name) ?: [$name];
        $initials = strtoupper(
            mb_substr($parts[0] ?? '', 0, 1)
            . (count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '')
        );
        $initials = $initials !== '' ? $initials : '?';

        // Deterministic hue from the name so the same person keeps the same colour.
        $hue = crc32($name) % 360;
        $bg = "hsl({$hue} 52% 46%)";

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64">'
            . "<rect width='64' height='64' rx='32' fill='{$bg}'/>"
            . "<text x='50%' y='50%' dy='.35em' text-anchor='middle' "
            . "font-family='Inter, Arial, sans-serif' font-size='26' font-weight='600' "
            . "fill='#ffffff'>" . htmlspecialchars($initials, ENT_QUOTES) . '</text></svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
