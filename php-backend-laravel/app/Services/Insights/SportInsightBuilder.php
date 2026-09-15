<?php

declare(strict_types=1);

namespace App\Services\Insights;

/**
 * One sport's reading of a match. Returns:
 *   players   — cards, best first (InsightContext::card)
 *   team      — the sport's own head-to-head sections, shaped for that sport's tab
 *   reads     — up to three sentences, each checkable against a figure on the tab
 *   untracked — what the scorer does not record for this sport, so the tab can say so
 */
interface SportInsightBuilder
{
    /** @return array{players: array<int, array<string, mixed>>, team: array<string, mixed>, reads: array<int, string|null>, untracked: array<int, string>} */
    public function build(InsightContext $ctx): array;
}
