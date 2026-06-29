<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Events\ContentUpdated;

/**
 * Emits a ContentUpdated signal whenever the model is created/updated/deleted so
 * connected clients refetch. Each model maps to a client "domain" (which
 * endpoint to refresh) via $contentDomain. Broadcasting goes through the default
 * connection — "log" locally, "reverb" in production — so it's safe to attach
 * even before Reverb is live.
 */
trait BroadcastsContentChanges
{
    public static function bootBroadcastsContentChanges(): void
    {
        static::saved(fn ($model) => ContentUpdated::dispatch($model->contentDomain()));
        static::deleted(fn ($model) => ContentUpdated::dispatch($model->contentDomain()));
    }

    /** Client domain to refetch. Override per model; defaults to the table name. */
    public function contentDomain(): string
    {
        return property_exists($this, 'contentDomain') && is_string($this->contentDomain)
            ? $this->contentDomain
            : $this->getTable();
    }
}
