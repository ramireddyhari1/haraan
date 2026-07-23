<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Events\ContentUpdated;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Emits a ContentUpdated signal whenever the model is created/updated/deleted so
 * connected clients refetch. Each model maps to a client "domain" (which
 * endpoint to refresh) via $contentDomain. Broadcasting goes through the default
 * connection — "log" locally, "reverb" in production — so it's safe to attach
 * even before Reverb is live.
 *
 * The broadcast is best-effort: with QUEUE_CONNECTION=sync it fires in-request, so
 * a realtime outage (e.g. Reverb down) would otherwise throw and fail the write.
 * We swallow + log any failure so a broadcasting hiccup can never break a save.
 */
trait BroadcastsContentChanges
{
    public static function bootBroadcastsContentChanges(): void
    {
        static::saved(fn ($model) => $model->broadcastContentChange());
        static::deleted(fn ($model) => $model->broadcastContentChange());
    }

    /** Fire the signal, never letting a broadcasting failure propagate to the write. */
    protected function broadcastContentChange(): void
    {
        try {
            ContentUpdated::dispatch($this->contentDomain());
        } catch (Throwable $e) {
            Log::warning('ContentUpdated broadcast failed', [
                'domain' => $this->contentDomain(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Client domain to refetch. Override per model; defaults to the table name. */
    public function contentDomain(): string
    {
        return property_exists($this, 'contentDomain') && is_string($this->contentDomain)
            ? $this->contentDomain
            : $this->getTable();
    }
}
