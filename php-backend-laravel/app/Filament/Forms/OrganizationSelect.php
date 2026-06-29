<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Models\OrganizationUnit;
use Filament\Forms\Components\Select;

/**
 * Reusable organization picker for Filament forms. Options are rendered as a
 * breadcrumb path ("State › District › Area") and are limited to the current
 * admin's scope — a district manager can only assign within their own subtree,
 * while super-admins / unassigned admins see every unit. Nullable: empty means
 * platform-wide.
 */
final class OrganizationSelect
{
    public static function make(string $field = 'organization_id'): Select
    {
        return Select::make($field)
            ->label('Organization')
            ->placeholder('Platform-wide (no organization)')
            ->options(fn (): array => self::options())
            ->searchable()
            ->preload()
            ->nullable();
    }

    /** @return array<int, string> */
    public static function options(): array
    {
        $allowed = auth()->user()?->scopedOrganizationIds(); // null = unrestricted

        $units = OrganizationUnit::with('parent')->get();

        $out = [];
        foreach ($units as $unit) {
            if ($allowed !== null && ! in_array($unit->id, $allowed, true)) {
                continue;
            }
            $out[$unit->id] = self::path($unit);
        }

        asort($out);

        return $out;
    }

    private static function path(OrganizationUnit $unit): string
    {
        $parts = [];
        $cursor = $unit;
        $guard = 0;
        while ($cursor !== null && $guard++ < 10) {
            array_unshift($parts, $cursor->name);
            $cursor = $cursor->parent;
        }

        return implode(' › ', $parts).'  ('.$unit->type.')';
    }
}
