<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\StandingContract;
use App\Services\StandingContractService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MaterializeStandingContracts extends Command
{
    protected $signature = 'venue:materialize-standing-contracts {--days=30 : Number of days ahead to materialize}';

    protected $description = 'Maintains a rolling 30-day horizon of bookings for all active standing contracts';

    public function handle(StandingContractService $service): int
    {
        $days = (int) $this->option('days');
        $this->info("Sweeping active standing contracts (horizon: {$days} days)...");

        $contracts = StandingContract::query()->active()->get();
        $totalGenerated = 0;

        foreach ($contracts as $contract) {
            try {
                $count = $service->materializeSessions($contract, $days);
                $contract->recalculateMetrics();
                $totalGenerated += $count;
            } catch (\Throwable $e) {
                $this->error("Error materializing contract #{$contract->id}: " . $e->getMessage());
                Log::error("MaterializeStandingContracts failure on #{$contract->id}: " . $e->getMessage());
            }
        }

        $this->info("Completed. Materialized {$totalGenerated} new sessions across {$contracts->count()} contracts.");

        return self::SUCCESS;
    }
}
