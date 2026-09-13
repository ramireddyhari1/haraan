<?php

declare(strict_types=1);

namespace App\Filament\Resources\Hrms\Pages;

use App\Filament\Resources\Hrms\EmployeeAttendanceResource;
use App\Models\Hrms\EmployeeAttendance;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListEmployeeAttendances extends ListRecords
{
    protected static string $resource = EmployeeAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export Attendance CSV')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportAttendanceCsv()),
        ];
    }

    private function exportAttendanceCsv(): StreamedResponse
    {
        $headers = ['Date', 'Employee Code', 'Employee Name', 'Shift', 'Clock In', 'Clock Out', 'Duration (Mins)', 'Geofence Status', 'Distance (Meters)', 'Status'];

        return response()->streamDownload(function () use ($headers): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);

            EmployeeAttendance::with(['employee.user', 'shift'])
                ->orderByDesc('date')
                ->chunk(200, function ($rows) use ($out): void {
                    foreach ($rows as $r) {
                        fputcsv($out, [
                            $r->date ? $r->date->format('Y-m-d') : '',
                            $r->employee?->employee_code,
                            $r->employee?->full_name,
                            $r->shift?->name,
                            $r->clock_in_at ? $r->clock_in_at->format('H:i:s') : '',
                            $r->clock_out_at ? $r->clock_out_at->format('H:i:s') : '',
                            $r->total_work_minutes,
                            $r->clock_in_geofence_status,
                            $r->clock_in_distance_meters,
                            $r->status,
                        ]);
                    }
                });

            fclose($out);
        }, 'attendance-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }
}
