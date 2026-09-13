<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Hrms\ApprovalEngineService;
use Illuminate\Console\Command;

final class EscalatePendingApprovalsCommand extends Command
{
    protected $signature = 'workforce:escalate-pending-approvals {--hours=24 : The SLA threshold in hours after which unadjudicated requests are escalated}';

    protected $description = 'Automatically escalate pending attendance regularisations and leave requests that exceed the SLA response threshold.';

    public function handle(ApprovalEngineService $approvalEngine): int
    {
        $hours = (int) ($this->option('hours') ?: config('workforce.approvals.sla_escalation_hours', 24));
        if ($hours <= 0) {
            $this->error('The SLA threshold hours must be greater than zero.');
            return self::FAILURE;
        }

        $this->info("Scanning workforce requests pending over {$hours} hours for SLA breach escalation...");

        $escalatedCount = $approvalEngine->escalateStaleRequests($hours);

        $this->info("Successfully escalated {$escalatedCount} request(s). Audit records logged to workforce_audit_ledger.");

        return self::SUCCESS;
    }
}
