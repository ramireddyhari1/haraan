<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\AdminAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes an audit entry whenever a signed-in console user (/control or /partner) creates,
 * changes or deletes one of these records — who, when, from which IP, and every changed field
 * as before → after.
 *
 * A model may narrow what counts with `protected array $auditedAttributes = [...]`: only
 * changes to those fields are logged (a User's heartbeat timestamp is not a sensitive change;
 * its role is). Secret-looking fields are always redacted and hidden fields never logged.
 *
 * Changes made by the system — queued jobs, webhooks, the app's JWT API, console commands —
 * have no console user and are not admin actions, so they aren't logged here.
 */
trait AuditsAdminChanges
{
    public static function bootAuditsAdminChanges(): void
    {
        static::created(fn (Model $m) => self::auditWrite($m, 'created'));
        static::updated(fn (Model $m) => self::auditWrite($m, 'updated'));
        static::deleted(fn (Model $m) => self::auditWrite($m, 'deleted'));
    }

    /** Return false to skip one record (e.g. a settings group that logs its own summary). */
    protected function shouldAuditChange(): bool
    {
        return true;
    }

    private static function auditWrite(Model $model, string $verb): void
    {
        if (Auth::guard('web')->id() === null || ! $model->shouldAuditChange()) {
            return;
        }

        $watched = property_exists($model, 'auditedAttributes') ? $model->auditedAttributes : null;
        $hidden = $model->getHidden();
        $changes = [];

        if ($verb === 'updated') {
            foreach ($model->getChanges() as $key => $to) {
                if ($key === 'updated_at' || ($watched !== null && ! in_array($key, $watched, true))) {
                    continue;
                }
                $changes[$key] = in_array($key, $hidden, true) || AdminAction::isSecretKey($key)
                    ? ['from' => '[redacted]', 'to' => '[redacted]']
                    : ['from' => $model->getOriginal($key), 'to' => $model->getAttribute($key)];
            }

            if ($changes === []) {
                return;
            }
        } else {
            $attributes = $model->getAttributes();
            foreach ($attributes as $key => $value) {
                if (in_array($key, ['created_at', 'updated_at'], true) || in_array($key, $hidden, true)
                    || ($watched !== null && ! in_array($key, $watched, true))) {
                    continue;
                }
                $changes[$key] = AdminAction::isSecretKey($key) ? '[redacted]' : $model->getAttribute($key);
            }
        }

        $name = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', class_basename($model)));

        AdminAction::log("{$name}.{$verb}", ['changes' => $changes], $model);
    }
}
