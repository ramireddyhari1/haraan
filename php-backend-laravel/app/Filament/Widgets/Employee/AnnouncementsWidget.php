<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Employee;

use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\HrmsAnnouncement;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class AnnouncementsWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.employee.announcements-widget';

    protected int | string | array $columnSpan = ['default' => 12, 'lg' => 12, 'xl' => 12];

    public static function canView(): bool
    {
        $employee = auth()->user()?->employeeProfile;
        $venueId = $employee?->venue_id;

        return HrmsAnnouncement::where('is_active', true)
            ->where(function ($query) use ($venueId): void {
                $query->where('audience', 'all')
                    ->orWhere('audience', 'partner_staff');
                if ($venueId !== null) {
                    $query->orWhere('venue_id', $venueId);
                }
            })
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })
            ->exists();
    }

    public function getEmployeeProperty(): ?EmployeeProfile
    {
        return auth()->user()?->employeeProfile;
    }

    public function getAnnouncementsProperty(): array
    {
        $employee = $this->employee;
        $venueId = $employee?->venue_id;

        return HrmsAnnouncement::where('is_active', true)
            ->where(function ($query) use ($venueId): void {
                $query->where('audience', 'all')
                    ->orWhere('audience', 'partner_staff');
                if ($venueId !== null) {
                    $query->orWhere('venue_id', $venueId);
                }
            })
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 ELSE 3 END")
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->all();
    }
}
