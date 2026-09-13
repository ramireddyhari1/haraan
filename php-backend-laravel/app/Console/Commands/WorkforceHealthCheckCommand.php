<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Hrms\WorkforceHealthService;
use Illuminate\Console\Command;

final class WorkforceHealthCheckCommand extends Command
{
    protected $signature = 'workforce:health-check
                            {--json : Output diagnostic results as JSON (suitable for monitoring agents)}';

    protected $description = 'Perform production health checks on Workforce OS (database, cache, cryptographic ledger, SLA backlog)';

    public function handle(WorkforceHealthService $healthService): int
    {
        $diagnostics = $healthService->checkHealth();

        if ($this->option('json')) {
            $this->output->writeln((string) json_encode($diagnostics, JSON_PRETTY_PRINT));
            return $diagnostics['status'] === 'unhealthy' ? self::FAILURE : self::SUCCESS;
        }

        $this->info("HARAAN Workforce Operating System — Production Health Review");
        $this->line("Timestamp:   {$diagnostics['timestamp']}");
        $this->line("Duration:    {$diagnostics['duration_ms']} ms");

        $statusTag = match ($diagnostics['status']) {
            'healthy' => '<fg=green;options=bold>HEALTHY</>',
            'degraded' => '<fg=yellow;options=bold>DEGRADED</>',
            default => '<fg=red;options=bold>UNHEALTHY</>',
        };
        $this->line("Status:      {$statusTag}");
        $this->line('');

        $tableRows = [];
        foreach ($diagnostics['checks'] as $name => $check) {
            $statusLabel = match ($check['status']) {
                'pass' => '<fg=green>PASS</>',
                'warn' => '<fg=yellow>WARN</>',
                default => '<fg=red>FAIL</>',
            };

            $tableRows[] = [
                ucwords(str_replace('_', ' ', $name)),
                $statusLabel,
                $check['message'],
            ];
        }

        $this->table(['Subsystem', 'Status', 'Diagnostics'], $tableRows);

        return $diagnostics['status'] === 'unhealthy' ? self::FAILURE : self::SUCCESS;
    }
}
