<?php

declare(strict_types=1);

namespace App\Services\Hrms;

use App\Models\Hrms\EmployeeAttendanceRegularisation;
use App\Models\Hrms\EmployeeLeaveRequest;
use App\Models\Hrms\WorkforceAuditLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class WorkforceHealthService
{
    public function __construct(
        private readonly WorkforceAuditService $auditService
    ) {}

    /**
     * Run full production diagnostics on Workforce OS subsystems.
     *
     * @return array{
     *     status: 'healthy'|'degraded'|'unhealthy',
     *     timestamp: string,
     *     duration_ms: float,
     *     checks: array<string, array{status: string, message: string, details?: mixed}>
     * }
     */
    public function checkHealth(): array
    {
        $startTime = microtime(true);
        $checks = [];
        $overallStatus = 'healthy';

        // 1. Database Connectivity & Table Accessibility
        try {
            $dbStart = microtime(true);
            DB::connection()->getPdo();
            $ledgerCount = WorkforceAuditLedger::count();
            $dbDuration = round((microtime(true) - $dbStart) * 1000, 2);

            $checks['database'] = [
                'status' => 'pass',
                'latency_ms' => $dbDuration,
                'message' => "Database accessible. Total ledger entries: {$ledgerCount}",
            ];
        } catch (Throwable $e) {
            $overallStatus = 'unhealthy';
            $checks['database'] = [
                'status' => 'fail',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }

        // 2. Cache & Idempotency Store
        try {
            $cachePingKey = 'workforce_health_ping_' . uniqid('', true);
            Cache::put($cachePingKey, 'ok', 10);
            $cacheVal = Cache::get($cachePingKey);
            Cache::forget($cachePingKey);

            if ($cacheVal === 'ok') {
                $checks['cache_idempotency_store'] = [
                    'status' => 'pass',
                    'message' => 'Cache read/write verified for idempotency tracking.',
                ];
            } else {
                $overallStatus = 'degraded';
                $checks['cache_idempotency_store'] = [
                    'status' => 'fail',
                    'message' => 'Cache ping value mismatch.',
                ];
            }
        } catch (Throwable $e) {
            $overallStatus = 'unhealthy';
            $checks['cache_idempotency_store'] = [
                'status' => 'fail',
                'message' => 'Cache store inaccessible: ' . $e->getMessage(),
            ];
        }

        // 3. Cryptographic Audit Ledger Integrity
        try {
            $verification = $this->auditService->verifyChainIntegrity();
            if ($verification['is_valid']) {
                $checks['cryptographic_audit_ledger'] = [
                    'status' => 'pass',
                    'verified_records' => $verification['verified_count'],
                    'message' => 'Hash chain unbroken. Cryptographic signatures valid.',
                ];
            } else {
                $overallStatus = 'unhealthy';
                $checks['cryptographic_audit_ledger'] = [
                    'status' => 'fail',
                    'tampered_record_id' => $verification['tampered_record_id'],
                    'message' => 'Tampering or hash chain break detected: ' . $verification['reason'],
                ];
            }
        } catch (Throwable $e) {
            $overallStatus = 'unhealthy';
            $checks['cryptographic_audit_ledger'] = [
                'status' => 'fail',
                'message' => 'Ledger verification error: ' . $e->getMessage(),
            ];
        }

        // 4. Pending Approval SLA Backlog
        try {
            $slaHours = (int) config('workforce.approvals.sla_escalation_hours', 24);
            $thresholdTime = Carbon::now()->subHours($slaHours);

            $staleRegs = EmployeeAttendanceRegularisation::whereIn('status', ['pending', 'tier1_pending', 'tier2_pending'])
                ->where('created_at', '<', $thresholdTime)
                ->count();

            $staleLeaves = EmployeeLeaveRequest::whereIn('status', ['pending', 'tier1_pending', 'tier2_pending'])
                ->where('created_at', '<', $thresholdTime)
                ->count();

            $totalStale = $staleRegs + $staleLeaves;

            if ($totalStale > 10) {
                if ($overallStatus !== 'unhealthy') {
                    $overallStatus = 'degraded';
                }
                $checks['sla_backlog'] = [
                    'status' => 'warn',
                    'stale_count' => $totalStale,
                    'message' => "High SLA backlog: {$totalStale} requests pending beyond {$slaHours}h SLA threshold.",
                ];
            } else {
                $checks['sla_backlog'] = [
                    'status' => 'pass',
                    'stale_count' => $totalStale,
                    'message' => "SLA backlog healthy: {$totalStale} requests past SLA threshold.",
                ];
            }
        } catch (Throwable $e) {
            $checks['sla_backlog'] = [
                'status' => 'warn',
                'message' => 'SLA check exception: ' . $e->getMessage(),
            ];
        }

        $totalDurationMs = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'status' => $overallStatus,
            'timestamp' => Carbon::now()->toIso8601String(),
            'duration_ms' => $totalDurationMs,
            'checks' => $checks,
        ];
    }
}
