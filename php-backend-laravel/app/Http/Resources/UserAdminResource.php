<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserAdminResource extends JsonResource
{
    /**
     * Transform the resource into an array with role-tiered PII masking.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();

        // Super-admins (ADMIN, COADMIN) or operators with explicit PII permission see full data.
        // Users inspecting their own account also see their full unmasked data.
        $canViewPii = $actor !== null && (
            $actor->isSuperAdmin()
            || (method_exists($actor, 'can') && $actor->can('users.pii.view'))
            || (int) $actor->id === (int) $this->id
        );

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $canViewPii ? $this->email : self::maskEmail($this->email),
            'phone' => $canViewPii ? $this->phone : self::maskPhone($this->phone),
            'avatar' => $this->avatar,
            'role' => $this->role,
            'status' => $this->status,
            'partner_type' => $this->partner_type,
            'event_host_id' => $this->event_host_id,
            'player_id' => $this->player_id,
            'player_role' => $this->player_role,
            'playing_style' => $this->playing_style,
            'district' => $this->district,
            'state' => $this->state,
            'gender' => $this->gender,
            'date_of_birth' => $canViewPii
                ? ($this->date_of_birth instanceof \DateTimeInterface ? $this->date_of_birth->format('Y-m-d') : $this->date_of_birth)
                : self::maskDate($this->date_of_birth),
            'age' => $this->age,
            'birth_place' => $canViewPii ? $this->birth_place : null,
            'nationality' => $canViewPii ? $this->nationality : null,
            'trust_score' => $this->trust_score ?? 100,
            'is_verified' => (bool) $this->is_verified,
            'verified_at' => $this->verified_at,
            'is_organizer' => (bool) $this->is_organizer,
            'last_seen_at' => $this->last_seen_at,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'is_pii_masked' => ! $canViewPii,
        ];
    }

    /**
     * Mask phone number, preserving last 4 digits.
     * e.g. "+91 9876543210" -> "*********3210"
     */
    public static function maskPhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $trimmed = trim($phone);
        $len = strlen($trimmed);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        $last4 = substr($trimmed, -4);
        return str_repeat('*', $len - 4) . $last4;
    }

    /**
     * Mask email address.
     * e.g. "hariharan@example.com" -> "h***n@example.com"
     */
    public static function maskEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        $parts = explode('@', trim($email), 2);
        if (count($parts) < 2) {
            return '***';
        }

        [$name, $domain] = $parts;
        $nameLen = strlen($name);

        if ($nameLen <= 2) {
            $maskedName = substr($name, 0, 1) . '***';
        } else {
            $maskedName = substr($name, 0, 1) . '***' . substr($name, -1);
        }

        return $maskedName . '@' . $domain;
    }

    /**
     * Mask date of birth, showing year only or masked month/day.
     * e.g. "1995-08-25" -> "1995-**-**"
     */
    public static function maskDate(mixed $date): ?string
    {
        if ($date === null) {
            return null;
        }

        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y') . '-**-**';
        }

        $dateStr = trim((string) $date);
        if ($dateStr === '') {
            return null;
        }

        if (preg_match('/^(\d{4})/', $dateStr, $matches)) {
            return $matches[1] . '-**-**';
        }

        return '****-**-**';
    }
}
