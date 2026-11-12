<?php

declare(strict_types=1);

namespace App\Support;

/**
 * "One empty-state drawing per page."
 *
 * A page full of empty panels used to draw a line-art illustration in every one of
 * them — six on Events Overview — which reads as a template, not a product. The
 * first empty panel in a render claims the drawing; every later one is a plain
 * line of text. Scoped to the current request, so each Livewire re-render
 * starts fresh.
 */
final class EmptyArt
{
    /** True if a drawing was already used in this request; claims it otherwise. */
    public static function taken(): bool
    {
        $key = 'hrn.empty-art.used';
        if (app()->bound($key)) {
            return true;
        }
        app()->instance($key, true);

        return false;
    }
}
