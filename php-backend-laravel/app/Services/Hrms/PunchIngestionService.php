<?php

declare(strict_types=1);

namespace App\Services\Hrms;

use App\Jobs\Hrms\ProcessPunchJob;
use App\Models\Hrms\EmployeeProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class PunchIngestionService
{
    /**
     * Ingest a punch event with idempotency guarantees.
     * Discards redundant duplicate transmissions and dispatches processing.
     *
     * @return array{status: string, receipt: array, is_duplicate: bool}
     */
    public function ingestPunch(
        EmployeeProfile $employee,
        string $punchType,
        float $latitude,
        float $longitude,
        string $idempotencyKey,
        string $method = 'gps_web',
        ?string $photoPath = null,
        bool $sync = true
    ): array {
        $cacheKey = "punch_idempotency:{$employee->id}:{$idempotencyKey}";

        // Idempotency Deduplication Check
        if (Cache::has($cacheKey)) {
            $cachedReceipt = (array) Cache::get($cacheKey);
            return [
                'status' => 'acknowledged',
                'receipt' => $cachedReceipt,
                'is_duplicate' => true,
            ];
        }

        $timestamp = Carbon::now()->toIso8601String();

        $receipt = [
            'idempotency_key' => $idempotencyKey,
            'employee_code' => $employee->employee_code,
            'punch_type' => $punchType,
            'timestamp' => $timestamp,
            'method' => $method,
            'venue_id' => $employee->venue_id,
        ];

        // Cache the transaction receipt with configured TTL to prevent duplicate replays
        $ttlHours = (int) config('workforce.ingestion.idempotency_ttl_hours', 24);
        Cache::put($cacheKey, $receipt, Carbon::now()->addHours($ttlHours));

        if ($sync) {
            ProcessPunchJob::dispatchSync(
                $employee->id,
                $punchType,
                $timestamp,
                $latitude,
                $longitude,
                $method,
                $idempotencyKey,
                $photoPath
            );
        } else {
            ProcessPunchJob::dispatch(
                $employee->id,
                $punchType,
                $timestamp,
                $latitude,
                $longitude,
                $method,
                $idempotencyKey,
                $photoPath
            );
        }

        return [
            'status' => 'accepted',
            'receipt' => $receipt,
            'is_duplicate' => false,
        ];
    }
}
