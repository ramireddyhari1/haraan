<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use App\Models\Hrms\EmployeeAttendance;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class PartnerAttendancePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $title = 'Floor Attendance Monitor';

    protected static ?string $navigationLabel = 'Floor Attendance';

    protected static string|\UnitEnum|null $navigationGroup = 'Team & HRMS';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.partner.attendance';

    public string $filterDate = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'partner'
            && $user !== null
            && ! $user->isDeskStaff();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->filterDate = Carbon::today()->toDateString();
    }

    public function getAttendancesProperty(): array
    {
        $partnerId = auth()->user()?->effectivePartnerId();

        return EmployeeAttendance::with(['employee.user', 'shift'])
            ->whereHas('employee', fn ($q) => $q->where('partner_id', $partnerId))
            ->whereDate('date', $this->filterDate)
            ->orderByDesc('clock_in_at')
            ->get()
            ->all();
    }
}
