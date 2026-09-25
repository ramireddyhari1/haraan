<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * The admin audit log. Append-only: an entry can be written, never changed or removed — the
 * model refuses updates and deletes, and /control has no create/edit/delete for it.
 *
 * Meta never carries a secret: {@see self::redact()} blanks any key that looks like one before
 * the row is written, whoever the caller is.
 */
final class AdminAction extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'meta', 'ip'];

    protected $casts = [
        'meta' => 'array',
    ];

    /** Keys whose values are never written to the log. */
    private const SECRET_KEYS = ['password', 'secret', 'token', 'api_key', 'apikey', 'private_key', 'remember_token', 'otp', 'credential', 'signature'];

    protected static function booted(): void
    {
        self::updating(fn () => throw new \LogicException('Audit entries are append-only.'));
        self::deleting(fn () => throw new \LogicException('Audit entries are append-only.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an admin action for the audit trail. Captures the acting user and IP
     * automatically. e.g. AdminAction::log('booking.confirmed', ['booking_id' => 7]).
     */
    public static function log(string $action, array $meta = [], ?Model $subject = null): void
    {
        static::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject !== null ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'meta' => self::redact($meta),
            'ip' => Request::ip(),
        ]);
    }

    /** @param  array<mixed>  $meta */
    public static function redact(array $meta): array
    {
        foreach ($meta as $key => $value) {
            if (is_string($key) && self::isSecretKey($key)) {
                $meta[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $meta[$key] = self::redact($value);
            }
        }

        return $meta;
    }

    public static function isSecretKey(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::SECRET_KEYS as $marker) {
            if (str_contains($lower, $marker)) {
                return true;
            }
        }

        return false;
    }

    /** One readable line per meta entry, for the audit table. Nested values are flattened. */
    public function summary(): string
    {
        $meta = $this->meta;
        if (! is_array($meta)) {
            return (string) $meta;
        }

        if (isset($meta['changes']) && is_array($meta['changes'])) {
            return collect($meta['changes'])
                ->map(fn ($c, $k): string => is_array($c) && array_key_exists('from', $c)
                    ? "{$k}: ".self::scalar($c['from']).' → '.self::scalar($c['to'] ?? null)
                    : "{$k}: ".self::scalar($c))
                ->implode(' · ');
        }

        return collect($meta)->map(fn ($v, $k): string => "{$k}: ".self::scalar($v))->implode(' · ');
    }

    private static function scalar(mixed $v): string
    {
        return match (true) {
            $v === null => '—',
            is_bool($v) => $v ? 'on' : 'off',
            is_array($v) => mb_strimwidth((string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 160, '…'),
            default => mb_strimwidth((string) $v, 0, 160, '…'),
        };
    }
}
