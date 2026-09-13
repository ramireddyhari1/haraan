<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Venue;
use App\Services\Hrms\LaborForecastingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class ForecastLaborCostCommand extends Command
{
    protected $signature = 'workforce:forecast-labor
                            {venue_id : The ID of the venue}
                            {--month= : Target month YYYY-MM (defaults to current month)}';

    protected $description = 'Forecast labor cost vs booking revenue and flag statutory overtime threshold risks';

    public function handle(LaborForecastingService $forecaster): int
    {
        $venueId = (int) $this->argument('venue_id');
        $venue = Venue::find($venueId);

        if (! $venue) {
            $this->error("Venue #{$venueId} not found.");
            return self::FAILURE;
        }

        $month = $this->option('month') ?: Carbon::now()->format('Y-m');

        $this->info("Calculating Predictive Labor Forecast for [{$venue->name}] ({$month})...");

        $forecast = $forecaster->forecastMonthly($venue, $month);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Projected Booking Revenue', '₹' . number_format($forecast['projected_revenue'], 2)],
                ['Projected Labor Expenses', '₹' . number_format($forecast['projected_labor_cost'], 2)],
                ['Labor Cost Percentage (LCP)', $forecast['labor_cost_percentage'] . '%'],
                ['Total Scheduled Hours', $forecast['total_scheduled_hours'] . ' hrs'],
                ['Projected Statutory Overtime', $forecast['projected_overtime_hours'] . ' hrs (₹' . number_format($forecast['projected_overtime_cost'], 2) . ')'],
                ['Health Status', $forecast['status']],
            ]
        );

        $this->line('');
        $this->comment("Recommendation: {$forecast['recommendation']}");

        if (! empty($forecast['alerts'])) {
            $this->line('');
            $this->warn('--- Statutory Overtime & Exhaustion Alerts ---');
            $alertRows = array_map(function ($a) {
                return [
                    'Severity' => strtoupper($a['severity']),
                    'Type' => $a['type'],
                    'Employee' => $a['employee_name'] . ' (' . $a['employee_code'] . ')',
                    'Message' => $a['message'],
                ];
            }, $forecast['alerts']);

            $this->table(['Severity', 'Type', 'Employee', 'Message'], $alertRows);
        } else {
            $this->info('Zero statutory labor compliance risks detected.');
        }

        return self::SUCCESS;
    }
}
