<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Venues\Pages\EditVenue;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCourt;
use App\Models\VenueSlot;
use App\Support\PlatformRules;
use App\Support\VenuePhoto;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Venue photos were served exactly as uploaded: a 3.5 MB, 4032px PNG to a 92px
 * thumbnail and a phone card alike, and WhatsApp-shrunk or portrait shots went in
 * unchallenged. Saving a venue now makes sized WebP copies the API and pages use,
 * and /control refuses a new photo that will look bad, saying why.
 */
final class VenuePhotoTest extends TestCase
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

    private function venue(array $images): Venue
    {
        $venue = Venue::create([
            'name' => 'Shuttle House', 'location' => 'Labbipet', 'city' => 'Vijayawada', 'price' => 400,
            'images' => $images, 'status' => 'published', 'published_at' => now(),
            'is_active' => true, 'is_bookable' => true,
        ]);
        VenueCourt::create(['venue_id' => $venue->id, 'name' => 'Court 1', 'price' => 400, 'is_active' => true]);
        VenueSlot::create(['venue_id' => $venue->id, 'day' => 'Daily', 'time' => '6:00 AM', 'price' => 400, 'is_available' => true]);

        return $venue;
    }

    public function test_saving_a_venue_makes_sized_copies_and_never_upscales(): void
    {
        $this->venue([$this->photo('venues/wide.jpg', 2000, 1125), $this->photo('venues/small.jpg', 1280, 576)]);

        $this->assertSame([480, 960, 1600, 2000], array_keys(VenuePhoto::variantsOf('venues/wide.jpg')));
        $this->assertSame([480, 960, 1280], array_keys(VenuePhoto::variantsOf('venues/small.jpg')));

        [$w, $h] = getimagesize(Storage::disk('public')->path('variants/venues/wide.jpg-1600w.webp'));
        $this->assertSame([1600, 900], [$w, $h]);
    }

    public function test_the_api_serves_the_1600_copy_and_every_width(): void
    {
        $venue = $this->venue([$this->photo('venues/wide.jpg', 3000, 1688), 'https://cdn.example.com/pasted.jpg']);

        $detail = $this->getJson("/api/venues/{$venue->id}")->assertOk()->json('data');
        $this->assertStringEndsWith('variants/venues/wide.jpg-1600w.webp', $detail['images'][0]);
        $this->assertSame('https://cdn.example.com/pasted.jpg', $detail['images'][1]);
        $this->assertSame([480, 960, 1600, 2400], array_column($detail['image_sets'][0], 'w'));
        $this->assertSame([], $detail['image_sets'][1], 'A pasted link has no copies and keeps its own URL.');

        $card = collect($this->getJson('/api/venues')->assertOk()->json('data'))->firstWhere('id', $venue->id);
        $this->assertStringEndsWith('-1600w.webp', $card['image']);
        $this->assertCount(2, $card['image_sets']);
    }

    public function test_a_photo_without_copies_falls_back_to_the_original(): void
    {
        $this->assertStringEndsWith('/storage/venues/not-made-yet.jpg', VenuePhoto::url('venues/not-made-yet.jpg'));
        $this->assertNull(VenuePhoto::srcset('venues/not-made-yet.jpg'));
        $this->assertSame('', VenuePhoto::imgAttrs('venues/not-made-yet.jpg', '100vw'));
    }

    public function test_views_get_a_srcset_from_the_url_they_hold(): void
    {
        $this->venue([$this->photo('venues/wide.jpg', 2000, 1125)]);

        $attrs = VenuePhoto::imgAttrs(VenuePhoto::url('venues/wide.jpg'), '100vw');
        $this->assertStringContainsString('wide.jpg-480w.webp 480w', $attrs);
        $this->assertStringContainsString('wide.jpg-2000w.webp 2000w', $attrs);
        $this->assertStringContainsString('sizes="100vw"', $attrs);
    }

    public function test_removing_a_photo_deletes_its_copies(): void
    {
        $venue = $this->venue([$this->photo('venues/a.jpg', 1600, 900), $this->photo('venues/b.jpg', 1600, 900)]);
        $this->assertNotSame([], VenuePhoto::variantsOf('venues/a.jpg'));

        $venue->update(['images' => ['venues/b.jpg']]);

        $this->assertSame([], VenuePhoto::variantsOf('venues/a.jpg'));
        $this->assertNotSame([], VenuePhoto::variantsOf('venues/b.jpg'));
    }

    public function test_the_upload_check_refuses_small_and_portrait_photos_and_admin_can_tune_it(): void
    {
        $disk = Storage::disk('public');

        $this->assertNull(VenuePhoto::uploadProblem($disk->path($this->photo('t/good.jpg', 1600, 900))));
        $this->assertStringContainsString('1280×576', (string) VenuePhoto::uploadProblem($disk->path($this->photo('t/whatsapp.jpg', 1280, 576))));
        $this->assertStringContainsString('taller than it is wide', (string) VenuePhoto::uploadProblem($disk->path($this->photo('t/poster.jpg', 1200, 1500))));

        PlatformRules::save(['venue_photos.min_height' => 500]);
        $this->assertNull(VenuePhoto::uploadProblem($disk->path('t/whatsapp.jpg')));

        PlatformRules::save(['venue_photos.require_landscape' => false]);
        $this->assertNull(VenuePhoto::uploadProblem($disk->path('t/poster.jpg')));

        PlatformRules::save(['venue_photos.check_uploads' => false, 'venue_photos.min_height' => 600]);
        $this->assertNull(VenuePhoto::uploadProblem($disk->path('t/whatsapp.jpg')));
    }

    public function test_control_refuses_a_bad_new_upload_but_never_an_existing_photo(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('control'));
        $this->actingAs(User::create([
            'name' => 'Ops Operator', 'email' => 'ops@haraan.test', 'password' => bcrypt('Password123!'),
            'role' => 'ADMIN', 'status' => 'ACTIVE',
        ]));
        $venue = $this->venue([$this->photo('venues/whatsapp.jpg', 1280, 576)]);

        // The photo already on it fails today's bar; editing anything else still saves.
        Livewire::test(EditVenue::class, ['record' => $venue->getRouteKey()])
            ->fillForm(['name' => 'Shuttle House Labbipet'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Shuttle House Labbipet', $venue->fresh()->name);

        Livewire::test(EditVenue::class, ['record' => $venue->getRouteKey()])
            ->fillForm(['images' => [UploadedFile::fake()->image('poster.jpg', 900, 1400)]])
            ->call('save')
            ->assertHasFormErrors(['images']);
        $this->assertSame(['venues/whatsapp.jpg'], $venue->fresh()->images);
    }

    public function test_the_partner_form_carries_the_same_bar_as_a_warning(): void
    {
        $this->get('/become-a-partner')->assertOk()->assertSee('"photoCheck":{"w":1200,"h":600,"landscape":true}', false);

        PlatformRules::save(['venue_photos.partner_warning' => false]);
        $this->get('/become-a-partner')->assertOk()->assertSee('"photoCheck":null', false);
    }
}
