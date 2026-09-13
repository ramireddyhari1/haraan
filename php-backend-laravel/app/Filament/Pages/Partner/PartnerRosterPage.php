<?php

declare(strict_types=1);

namespace App\Filament\Pages\Partner;

use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeShift;
use App\Models\Hrms\EmployeeShiftRoster;
use App\Models\Venue;
use App\Services\Hrms\RosterService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Throwable;

class PartnerRosterPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $title = 'Team Shift Roster';

    protected static ?string $navigationLabel = 'Shift Scheduler';

    protected static string|\UnitEnum|null $navigationGroup = 'Team & HRMS';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.partner.roster';

    public string $selectedDate = '';
    public bool $showAssignModal = false;
    public ?int $selectedEmployeeId = null;
    public ?int $selectedShiftId = null;
    public ?int $selectedVenueId = null;

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
        $this->selectedDate = Carbon::today()->toDateString();
    }

    public function getStaffProperty(): array
    {
        $partnerId = auth()->user()?->effectivePartnerId();

        return EmployeeProfile::with('user')
            ->where('partner_id', $partnerId)
            ->where('employment_status', 'active')
            ->get()
            ->all();
    }

    public function getVenuesProperty(): array
    {
        $partnerId = auth()->user()?->effectivePartnerId();

        return Venue::where('partner_id', $partnerId)->get()->all();
    }

    public function getShiftsProperty(): array
    {
        return EmployeeShift::where('is_active', true)->get()->all();
    }

    public function getRostersProperty(): array
    {
        $partnerId = auth()->user()?->effectivePartnerId();

        return EmployeeShiftRoster::with(['employee.user', 'shift', 'venue'])
            ->whereHas('employee', fn ($q) => $q->where('partner_id', $partnerId))
            ->whereDate('roster_date', $this->selectedDate)
            ->get()
            ->all();
    }

    public function openAssignModal(): void
    {
        $this->showAssignModal = true;
    }

    public function closeAssignModal(): void
    {
        $this->showAssignModal = false;
    }

    public function assignShift(): void
    {
        if ($this->selectedEmployeeId === null || $this->selectedShiftId === null) {
            Notification::make()->title('Please select both an employee and a shift')->danger()->send();
            return;
        }

        $employee = EmployeeProfile::find($this->selectedEmployeeId);
        $shift = EmployeeShift::find($this->selectedShiftId);
        $venue = $this->selectedVenueId ? Venue::find($this->selectedVenueId) : null;

        if ($employee === null || $shift === null) {
            return;
        }

        try {
            $service = app(RosterService::class);
            $service->assignShift($employee, $shift, Carbon::parse($this->selectedDate), $venue);

            Notification::make()->title('Shift Scheduled')->success()->send();
            $this->showAssignModal = false;
            $this->selectedEmployeeId = null;
            $this->selectedShiftId = null;
        } catch (Throwable $e) {
            Notification::make()->title('Assignment Error')->body($e->getMessage())->danger()->send();
        }
    }
}
