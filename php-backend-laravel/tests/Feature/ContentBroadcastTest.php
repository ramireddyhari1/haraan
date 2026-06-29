<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ContentUpdated;
use App\Models\FeatureFlag;
use App\Models\HomeBlock;
use App\Models\Translation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ContentBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_models_broadcasts_their_domain(): void
    {
        Event::fake([ContentUpdated::class]);

        FeatureFlag::create(['key' => 'k', 'name' => 'K', 'enabled' => true, 'rollout_percentage' => 100]);
        HomeBlock::create(['type' => 'hero', 'is_active' => true, 'sort_order' => 1]);
        Translation::create(['key' => 'a.b', 'locale' => 'en', 'value' => 'X']);

        Event::assertDispatched(ContentUpdated::class, fn ($e) => $e->domain === 'config');
        Event::assertDispatched(ContentUpdated::class, fn ($e) => $e->domain === 'home');
        Event::assertDispatched(ContentUpdated::class, fn ($e) => $e->domain === 'i18n');
    }

    public function test_event_broadcasts_on_public_content_channel(): void
    {
        $event = new ContentUpdated('config');

        $this->assertSame('content', $event->broadcastOn()->name);
        $this->assertSame('content.updated', $event->broadcastAs());
        $this->assertSame('config', $event->broadcastWith()['domain']);
    }
}
