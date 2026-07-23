<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Event;
use Filament\Widgets\Widget;

/**
 * "Share your event" — one copy button per channel that appends the matching
 * ?src= tag to the public event URL, so every open is attributed automatically
 * in Traffic Sources / the funnel (EventViewRecorder reads ?src first). Closes
 * the loop the traffic breakdown opens: it's the tool that makes that data clean.
 *
 * Read-only; the bound record is injected by the analytics page. Self-contained
 * Blade + inline CSS/JS (clipboard), no Vite rebuild.
 */
class EventShareLinksWidget extends Widget
{
    public ?Event $record = null;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.resources.events.widgets.event-share-links';

    /** Channels offered, in share-priority order. `src` null = the plain link. */
    private const CHANNELS = [
        ['key' => 'instagram', 'label' => 'Instagram', 'src' => 'instagram', 'color' => '#e1306c'],
        ['key' => 'whatsapp', 'label' => 'WhatsApp', 'src' => 'whatsapp', 'color' => '#25d366'],
        ['key' => 'facebook', 'label' => 'Facebook', 'src' => 'facebook', 'color' => '#1877f2'],
        ['key' => 'shared', 'label' => 'Bio / newsletter', 'src' => 'shared', 'color' => '#8b5cf6'],
        ['key' => 'direct', 'label' => 'Plain link', 'src' => null, 'color' => '#94a3b8'],
    ];

    /**
     * @return array{base:string, channels:array<int,array{label:string,color:string,url:string}>}
     */
    public function getShare(): array
    {
        $event = $this->record;
        $base = $event ? url('/events/' . $event->id) : url('/');

        $channels = array_map(fn (array $c): array => [
            'label' => $c['label'],
            'color' => $c['color'],
            'url' => $c['src'] === null ? $base : $base . '?src=' . $c['src'],
        ], self::CHANNELS);

        return ['base' => $base, 'channels' => $channels];
    }
}
