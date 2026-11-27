<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Support\VenuePhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Event posters were sent full-size (one was 786 KB) to 300px cards. Saving an
 * event now makes the same sized WebP copies venue photos get; cards use the
 * 960 copy, the API and detail page the 1600 one, link previews the original.
 */
final class EventPhotoVariantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('Needs GD with WebP (php -d extension=gd).');
        }

        Storage::fake('public');
    }

    private function photo(string $path, int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 37, 99, 235));
        Storage::disk('public')->makeDirectory(dirname($path));
        imagejpeg($img, Storage::disk('public')->path($path), 90);
        imagedestroy($img);

        return $path;
    }

    private function event(array $images, array $gallery = []): Event
    {
        $partner = User::factory()->create();

        return Event::create([
            'partner_id' => $partner->id, 'title' => 'Poster Show', 'category' => 'Music',
            'location' => 'Arena', 'venue' => 'Arena, Hyderabad', 'date' => now()->addDays(5),
            'time' => '19:00', 'price' => 500, 'total_slots' => 50, 'available_slots' => 50,
            'images' => $images, 'gallery' => $gallery, 'status' => 'published',
        ]);
    }

    public function test_saving_an_event_makes_poster_and_gallery_copies(): void
    {
        $this->event([$this->photo('events/poster.jpg', 2000, 2500)], [$this->photo('events/g1.jpg', 1200, 800)]);

        $this->assertSame([480, 960, 1600, 2000], array_keys(VenuePhoto::variantsOf('events/poster.jpg')));
        $this->assertSame([480, 960, 1200], array_keys(VenuePhoto::variantsOf('events/g1.jpg')));
    }

    public function test_cards_api_and_link_previews_get_the_right_file(): void
    {
        $event = $this->event([$this->photo('events/poster.jpg', 2000, 2500), 'https://cdn.example.com/pasted.jpg']);

        $this->assertStringEndsWith('variants/events/poster.jpg-1600w.webp', $event->heroImageUrl());
        $this->assertStringEndsWith('variants/events/poster.jpg-960w.webp', $event->heroImageUrl(Event::CARD_IMAGE_WIDTH));
        $this->assertSame('https://cdn.example.com/pasted.jpg', $event->imageUrls()[1]);
        $this->assertStringEndsWith('storage/events/poster.jpg', $event->originalImageUrls()[0]);

        $this->getJson('/api/events/'.$event->id)
            ->assertOk()
            ->assertJsonPath('data.images.1', 'https://cdn.example.com/pasted.jpg');
        $this->assertStringEndsWith('-1600w.webp', (string) $this->getJson('/api/events/'.$event->id)->json('data.images.0'));
    }

    public function test_an_event_without_copies_falls_back_to_the_original(): void
    {
        $event = $this->event([]);
        $event->forceFill(['images' => ['events/later.jpg']])->saveQuietly();

        $this->assertStringEndsWith('storage/events/later.jpg', $event->heroImageUrl(Event::CARD_IMAGE_WIDTH));
    }
}
