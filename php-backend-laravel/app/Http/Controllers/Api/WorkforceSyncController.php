<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hrms\EmployeeProfile;
use App\Models\Hrms\EmployeeTask;
use App\Services\Hrms\PunchIngestionService;
use App\Services\Hrms\WorkforceAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

final class WorkforceSyncController extends Controller
{
    public function __construct(
        private readonly PunchIngestionService $ingestionService,
        private readonly WorkforceAuditService $auditService
    ) {}

    /**
     * Ingest a single high-velocity punch event.
     */
    public function punch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_code' => ['nullable', 'string'],
            'employee_profile_id' => ['nullable', 'integer'],
            'punch_type' => ['required', 'in:in,out'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'method' => ['nullable', 'string'],
            'photo_path' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        // Resolve target employee profile
        $employee = null;
        if (! empty($validated['employee_code'])) {
            $employee = EmployeeProfile::where('employee_code', $validated['employee_code'])->first();
        } elseif (! empty($validated['employee_profile_id'])) {
            $employee = EmployeeProfile::find($validated['employee_profile_id']);
        } elseif ($user?->employeeProfile) {
            $employee = $user->employeeProfile;
        }

        if (! $employee) {
            return response()->json(['error' => 'Employee profile not found.'], 404);
        }

        $idempotencyKey = $request->header('X-Idempotency-Key')
            ?? $request->input('idempotency_key')
            ?? (string) Str::uuid();

        $method = $validated['method'] ?? 'gps_web';

        $result = $this->ingestionService->ingestPunch(
            $employee,
            $validated['punch_type'],
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            $idempotencyKey,
            $method,
            $validated['photo_path'] ?? null,
            true // synchronous execution
        );

        $statusCode = $result['is_duplicate'] ? 200 : 201;

        return response()->json([
            'success' => true,
            'status' => $result['status'],
            'is_duplicate' => $result['is_duplicate'],
            'receipt' => $result['receipt'],
        ], $statusCode);
    }

    /**
     * Batch synchronization endpoint for offline mobile apps and turf turnstiles.
     */
    public function syncBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['nullable', 'string'],
            'events' => ['required', 'array', 'min:1'],
            'events.*.client_event_id' => ['required', 'string'],
            'events.*.event_type' => ['required', 'in:punch,task_completion'],
            'events.*.employee_code' => ['nullable', 'string'],
            'events.*.occurred_at' => ['required', 'string'],
            'events.*.payload' => ['required', 'array'],
        ]);

        $events = $validated['events'];

        // Monotonic Chronological Sorting
        usort($events, fn ($a, $b) => strcmp((string) $a['occurred_at'], (string) $b['occurred_at']));

        $processedCount = 0;
        $failedCount = 0;
        $results = [];

        foreach ($events as $event) {
            $eventId = $event['client_event_id'];
            $type = $event['event_type'];
            $occurredAt = Carbon::parse($event['occurred_at']);
            $payload = $event['payload'];

            try {
                if ($type === 'punch') {
                    $employee = null;
                    $employeeCode = $event['employee_code'] ?? ($payload['employee_code'] ?? null);

                    if (! empty($employeeCode)) {
                        $employee = EmployeeProfile::where('employee_code', $employeeCode)->first();
                    } elseif (! empty($payload['employee_profile_id'])) {
                        $employee = EmployeeProfile::find($payload['employee_profile_id']);
                    } elseif ($request->user()?->employeeProfile) {
                        $employee = $request->user()->employeeProfile;
                    }

                    if (! $employee) {
                        throw new \RuntimeException('Employee profile not resolved for punch event.');
                    }

                    $punchResult = $this->ingestionService->ingestPunch(
                        $employee,
                        $payload['punch_type'] ?? 'in',
                        (float) ($payload['latitude'] ?? 0.0),
                        (float) ($payload['longitude'] ?? 0.0),
                        $eventId,
                        $payload['method'] ?? 'offline_sync',
                        $payload['photo_path'] ?? null,
                        true
                    );

                    $results[] = [
                        'client_event_id' => $eventId,
                        'status' => 'processed',
                        'is_duplicate' => $punchResult['is_duplicate'],
                    ];
                    $processedCount++;
                } elseif ($type === 'task_completion') {
                    $taskId = (int) ($payload['task_id'] ?? 0);
                    $task = EmployeeTask::find($taskId);

                    if ($task) {
                        $task->status = 'completed';
                        $task->progress_percent = 100;
                        $task->completed_at = $occurredAt;
                        $task->save();

                        $this->auditService->recordEvent(
                            'OFFLINE_TASK_COMPLETED',
                            $task,
                            null,
                            $task->toArray(),
                            $task->employee?->user,
                            $task->venue_id,
                            ['device_id' => $validated['device_id'] ?? null, 'occurred_at' => $occurredAt->toIso8601String()]
                        );
                    }

                    $results[] = [
                        'client_event_id' => $eventId,
                        'status' => 'processed',
                    ];
                    $processedCount++;
                }
            } catch (Throwable $e) {
                $results[] = [
                    'client_event_id' => $eventId,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
                $failedCount++;
            }
        }

        return response()->json([
            'success' => $failedCount === 0,
            'total_received' => count($events),
            'processed_count' => $processedCount,
            'failed_count' => $failedCount,
            'results' => $results,
        ], 200);
    }
}
