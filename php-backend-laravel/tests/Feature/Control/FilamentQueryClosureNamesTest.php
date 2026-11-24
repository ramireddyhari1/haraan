<?php

declare(strict_types=1);

namespace Tests\Feature\Control;

use App\Filament\Clusters\Events\Pages\EventHistory;
use App\Filament\Resources\HomeFeedComments\Pages\ListHomeFeedComments;
use App\Filament\Resources\HomeFeedPosts\Pages\ListHomeFeedPosts;
use App\Filament\Resources\PartnerTaxProfiles\Pages\ListPartnerTaxProfiles;
use App\Filament\Resources\PostReports\Pages\ListPostReports;
use App\Filament\Resources\Waitlist\Pages\ListWaitlistEntries;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filament fills closure arguments BY NAME. `fn (Builder $q)` in a tab's
 * modifyQueryUsing or a filter's query() gets a model-less Builder from the
 * container (or null when untyped), and the page 500s only once that tab or
 * filter is used. Every tab and custom filter below once used `$q`.
 */
class FilamentQueryClosureNamesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret123', 'role' => 'ADMIN', 'status' => 'active']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('control'));
    }

    public function test_partner_tax_profiles_list_renders(): void
    {
        Livewire::test(ListPartnerTaxProfiles::class)->assertSuccessful();
    }

    public function test_every_waitlist_tab_renders(): void
    {
        foreach (['offered', 'waiting', 'recovered', 'all'] as $tab) {
            Livewire::test(ListWaitlistEntries::class)->set('activeTab', $tab)->assertSuccessful();
        }
    }

    public function test_every_home_feed_post_tab_renders(): void
    {
        foreach (['all', 'feed', 'pinned', 'reported', 'hidden', 'removed'] as $tab) {
            Livewire::test(ListHomeFeedPosts::class)->set('activeTab', $tab)->assertSuccessful();
        }
    }

    public function test_custom_filters_apply_without_error(): void
    {
        Livewire::test(ListHomeFeedPosts::class)->filterTable('user_id', ['value' => 1])->assertSuccessful();
        Livewire::test(ListHomeFeedComments::class)->filterTable('post_id', ['value' => 1])->assertSuccessful();
        Livewire::test(ListPostReports::class)->filterTable('post_id', ['value' => 1])->assertSuccessful();
        Livewire::test(EventHistory::class)->filterTable('status', 'published')->assertSuccessful();
    }
}
