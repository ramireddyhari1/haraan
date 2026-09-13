<?php

declare(strict_types=1);

namespace App\Services\Hrms;

use App\Models\Hrms\WorkforceAuditLedger;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class WorkforceAuditService
{
    /**
     * Dedicated signing key for workforce audit trail.
     * Uses configured dedicated key or derives a dedicated cryptographically strong key from app.key.
     */
    public function getDedicatedSigningKey(): string
    {
        $custom = config('workforce.audit.signing_key') ?? config('hrms.audit_signing_key') ?? env('WORKFORCE_AUDIT_SIGNING_KEY') ?? env('HRMS_AUDIT_SIGNING_KEY');
        if (! empty($custom)) {
            return (string) $custom;
        }

        $appKey = (string) (config('app.key') ?: 'haraan-default-fallback-key');
        return hash_hmac('sha256', 'haraan-workforce-cryptographic-audit-v1', $appKey);
    }

    /**
     * Produce canonical JSON representation with sorted keys.
     */
    private function canonicalJson(?array $data): string
    {
        if ($data === null) {
            return '';
        }
        ksort($data);
        return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Record an immutable, cryptographically chained event in the audit ledger.
     */
    public function recordEvent(
        string $eventName,
        Model $entity,
        ?array $payloadBefore,
        array $payloadAfter,
        ?User $actor = null,
        ?int $venueId = null,
        array $telemetry = []
    ): WorkforceAuditLedger {
        $tenantId = 1;
        $signingKey = $this->getDedicatedSigningKey();
        $eventUuid = (string) Str::uuid();
        $canonicalTime = Carbon::now('UTC')->format('Y-m-d H:i:s');

        // Retrieve latest block's signature hash for tamper-evident chaining
        $latestRecord = WorkforceAuditLedger::where('tenant_id', $tenantId)
            ->latest('id')
            ->first();

        $previousHash = $latestRecord ? $latestRecord->signature_hash : str_repeat('0', 64);

        $actorId = $actor?->id;
        $actorRole = $actor?->role ?? ($actor?->getRoleNames()->first() ?? 'SYSTEM');
        $entityType = get_class($entity);
        $entityId = (int) $entity->getKey();

        // Standardized canonical representation for signature
        $payloadToSign = implode('|', [
            $previousHash,
            $eventUuid,
            (string) $tenantId,
            (string) ($venueId ?? 0),
            (string) ($actorId ?? 0),
            $actorRole,
            $entityType,
            (string) $entityId,
            $eventName,
            $this->canonicalJson($payloadAfter),
            $canonicalTime,
        ]);

        $signatureHash = hash_hmac('sha256', $payloadToSign, $signingKey);

        return WorkforceAuditLedger::create([
            'event_uuid' => $eventUuid,
            'occurred_at' => $canonicalTime,
            'tenant_id' => $tenantId,
            'venue_id' => $venueId,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'impersonated_by_id' => null,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'event_name' => $eventName,
            'payload_before' => $payloadBefore,
            'payload_after' => $payloadAfter,
            'client_ip' => request()?->ip() ?? '127.0.0.1',
            'user_agent' => request()?->userAgent() ?? 'HARAAN-Internal-CLI',
            'device_fingerprint' => $telemetry['device_fingerprint'] ?? null,
            'geo_latitude' => $telemetry['geo_latitude'] ?? null,
            'geo_longitude' => $telemetry['geo_longitude'] ?? null,
            'telemetry_metadata' => empty($telemetry) ? null : $telemetry,
            'previous_event_hash' => $previousHash,
            'signature_hash' => $signatureHash,
        ]);
    }

    /**
     * Verify the cryptographic hash chain integrity.
     * Returns whether the ledger is unbroken or indicates the first tampered record.
     *
     * @return array{is_valid: bool, verified_count: int, tampered_record_id: int|null, reason: string|null}
     */
    public function verifyChainIntegrity(?int $tenantId = 1, int $limit = 1000): array
    {
        $records = WorkforceAuditLedger::where('tenant_id', $tenantId ?? 1)
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        if ($records->isEmpty()) {
            return [
                'is_valid' => true,
                'verified_count' => 0,
                'tampered_record_id' => null,
                'reason' => 'Ledger is empty; no events to verify.',
            ];
        }

        $signingKey = $this->getDedicatedSigningKey();
        $expectedPrevHash = str_repeat('0', 64);
        $verifiedCount = 0;

        foreach ($records as $record) {
            // 1. Verify previous hash link
            if ($record->previous_event_hash !== $expectedPrevHash) {
                return [
                    'is_valid' => false,
                    'verified_count' => $verifiedCount,
                    'tampered_record_id' => (int) $record->id,
                    'reason' => "Hash chain break at Record ID #{$record->id}: expected previous hash {$expectedPrevHash}, found {$record->previous_event_hash}",
                ];
            }

            // 2. Recompute HMAC-SHA256 signature
            $actorId = $record->actor_id;
            $actorRole = $record->actor_role;
            $entityType = $record->entity_type;
            $entityId = (int) $record->entity_id;
            $eventName = $record->event_name;
            $payloadAfter = $record->payload_after ?? [];
            $canonicalTime = Carbon::parse($record->occurred_at)->format('Y-m-d H:i:s');

            $payloadToSign = implode('|', [
                $record->previous_event_hash,
                $record->event_uuid,
                (string) $record->tenant_id,
                (string) ($record->venue_id ?? 0),
                (string) ($actorId ?? 0),
                $actorRole,
                $entityType,
                (string) $entityId,
                $eventName,
                $this->canonicalJson($payloadAfter),
                $canonicalTime,
            ]);

            $recalculatedSignature = hash_hmac('sha256', $payloadToSign, $signingKey);

            if ($recalculatedSignature !== $record->signature_hash) {
                return [
                    'is_valid' => false,
                    'verified_count' => $verifiedCount,
                    'tampered_record_id' => (int) $record->id,
                    'reason' => "Signature mismatch at Record ID #{$record->id}: payload or metadata has been altered after signing.",
                ];
            }

            $expectedPrevHash = $record->signature_hash;
            $verifiedCount++;
        }

        return [
            'is_valid' => true,
            'verified_count' => $verifiedCount,
            'tampered_record_id' => null,
            'reason' => "Hash chain unbroken. All {$verifiedCount} inspected records are cryptographically verified.",
        ];
    }
}
