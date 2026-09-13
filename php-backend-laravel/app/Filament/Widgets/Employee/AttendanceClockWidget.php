<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Employee;

use App\Models\Hrms\EmployeeAttendance;
use App\Models\Hrms\EmployeeProfile;
use App\Services\Hrms\AttendanceService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Throwable;

class AttendanceClockWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.employee.attendance-clock-widget';

    protected int | string | array $columnSpan = ['default' => 12, 'lg' => 8, 'xl' => 8];

    public ?float $latitude = null;
    public ?float $longitude = null;
    public ?float $distanceMeters = null;
    public string $geofenceStatus = 'unknown'; // inside, outside, exempt, unknown
    public ?string $faceSnapshotData = null;
    public bool $showQrModal = false;
    public string $qrToken = '';
    public int $qrTtl = 60;

    public function mount(): void
    {
        $this->refreshStatus();
    }

    public function getEmployeeProperty(): ?EmployeeProfile
    {
        return auth()->user()?->employeeProfile;
    }

    public function getTodayAttendanceProperty(): ?EmployeeAttendance
    {
        $employee = $this->employee;
        if ($employee === null) {
            return null;
        }

        return EmployeeAttendance::where('employee_profile_id', $employee->id)
            ->whereDate('date', Carbon::today())
            ->first();
    }

    public function updateLocation(float $lat, float $lon): void
    {
        $this->latitude = $lat;
        $this->longitude = $lon;

        $employee = $this->employee;
        if ($employee?->venue && $employee->venue->latitude !== null && $employee->venue->longitude !== null) {
            $service = app(AttendanceService::class);
            $this->distanceMeters = $service->calculateDistanceMeters(
                $lat,
                $lon,
                (float) $employee->venue->latitude,
                (float) $employee->venue->longitude
            );

            $radius = (int) ($employee->geofence_radius_meters ?: 200);
            $this->geofenceStatus = ($this->distanceMeters <= $radius) ? 'inside' : 'outside';
        } else {
            $this->geofenceStatus = 'exempt';
        }
    }

    public function clockIn(): void
    {
        $employee = $this->employee;
        if ($employee === null) {
            Notification::make()->title('Employee profile not found')->danger()->send();
            return;
        }

        $lat = $this->latitude ?? 0.0;
        $lon = $this->longitude ?? 0.0;

        try {
            $service = app(AttendanceService::class);
            $service->clockIn($employee, $lat, $lon, 'gps_web', $this->faceSnapshotData);

            Notification::make()
                ->title('Clock-in Successful')
                ->body('Your attendance has been recorded.')
                ->success()
                ->send();

            $this->refreshStatus();
        } catch (Throwable $e) {
            Notification::make()->title('Clock-in Failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function clockOut(): void
    {
        $employee = $this->employee;
        if ($employee === null) {
            return;
        }

        $lat = $this->latitude ?? 0.0;
        $lon = $this->longitude ?? 0.0;

        try {
            $service = app(AttendanceService::class);
            $service->clockOut($employee, $lat, $lon, 'gps_web');

            Notification::make()
                ->title('Clock-out Successful')
                ->body('Shift concluded. Thank you for your work today!')
                ->success()
                ->send();

            $this->refreshStatus();
        } catch (Throwable $e) {
            Notification::make()->title('Clock-out Failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function toggleBreak(): void
    {
        $employee = $this->employee;
        $att = $this->todayAttendance;

        if ($employee === null || $att === null) {
            return;
        }

        $service = app(AttendanceService::class);

        try {
            if ($att->isCurrentlyOnBreak()) {
                $service->endBreak($employee);
                Notification::make()->title('Break Concluded')->body('Welcome back on duty!')->info()->send();
            } else {
                $service->startBreak($employee, 'lunch');
                Notification::make()->title('Break Started')->body('Enjoy your break!')->info()->send();
            }
            $this->refreshStatus();
        } catch (Throwable $e) {
            Notification::make()->title('Action Failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function openQrModal(): void
    {
        $employee = $this->employee;
        if ($employee !== null) {
            $service = app(AttendanceService::class);
            $this->qrToken = $service->generateEmployeeQrToken($employee, 60);
            $this->showQrModal = true;
        }
    }

    public function closeQrModal(): void
    {
        $this->showQrModal = false;
    }

    private function refreshStatus(): void
    {
        // Re-calculate distance if coords are set
        if ($this->latitude !== null && $this->longitude !== null) {
            $this->updateLocation($this->latitude, $this->longitude);
        }
    }
}
