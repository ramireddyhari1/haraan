<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Venue;
use App\Services\Hrms\RosterAutoSchedulerService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class AutoScheduleRosterCommand extends Command
{
    protected $signature = 'workforce:auto-schedule-roster
                            {venue_id : The ID of the venue}
                            {--days=7 : Number of days to generate schedule for}
                            {--start-date= : Start date YYYY-MM-DD (defaults to tomorrow)}
                            {--min-staff=1 : Minimum staff per shift}';

    protected $description = 'Auto-schedule shift rosters correlating with upcoming court bookings and statutory rest intervals';

    public function handle(RosterAutoSchedulerService $scheduler): int
    {
        $venueId = (int) $this->argument('venue_id');
        $venue = Venue::find($venueId);

        if (! $venue) {
            $this->error("Venue #{$venueId} not found.");
            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));
        $startDateInput = $this->option('start-date');
        $startDate = $startDateInput ? Carbon::parse($startDateInput) : Carbon::tomorrow();
        $endDate = $startDate->copy()->addDays($days - 1);
        $minStaff = max(1, (int) $this->option('min-staff'));

        $this->info("Executing Auto-Scheduler for [{$venue->name}] from {$startDate->toDateString()} to {$endDate->toDateString()}...");

        $result = $scheduler->autoSchedule($venue, $startDate, $endDate, $minStaff);

        $this->info("Auto-scheduling complete. Created {$result['total_rosters_created']} roster assignments.");
        $this->info("Evaluated {$result['total_bookings_evaluated']} upcoming court bookings.");

        if (! empty($result['rosters'])) {
            $tableData = array_map(function ($r) {
                return [
                    'Date' => $r['date'],
                    'Shift' => $r['shift_name'],
                    'Code' => $r['employee_code'],
                    'Employee' => $r['employee_name'],
                ];
            }, array_slice($result['rosters'], 0, 20));

            $this->table(['Date', 'Shift', 'Code', 'Employee'], $tableData);

            if (count($result['rosters']) > 20) {
                $this->comment('...and ' . (count($result['rosters']) - 20) . ' more assignments.');
            }
        }

        return self::SUCCESS;
    }
}
