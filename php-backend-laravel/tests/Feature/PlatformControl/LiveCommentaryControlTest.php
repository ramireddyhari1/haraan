<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformControl;

use App\Filament\Pages\CommentaryRulesPage;
use App\Filament\Resources\MatchCommentary\Pages\ListMatchCommentarySessions;
use App\Models\AdminAction;
use App\Models\LiveMatch;
use App\Models\MatchCommentarySession;
use App\Models\User;
use App\Support\PlatformRules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** /control → GameHub → Live commentary and Commentary rules. */
class LiveCommentaryControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_rules_page_saves_and_audits(): void
    {
        $this->signIn();

        $this->get('/control/game-hub/live-commentary-rules')->assertOk()->assertSee('Live commentary');

        Livewire::test(CommentaryRulesPage::class)
            ->set('data.commentary__max_listeners', 250)
            ->set('data.commentary__enabled', false)
            ->call('save');

        $this->assertSame(250, PlatformRules::int('commentary.max_listeners'));
        $this->assertFalse(PlatformRules::bool('commentary.enabled'));
        $this->assertTrue(AdminAction::where('action', 'commentary.rules_updated')->exists());
    }

    public function test_take_off_air_ends_the_broadcast_and_is_audited(): void
    {
        Http::fake();
        config(['services.livekit.url' => 'wss://lk.test', 'services.livekit.key' => 'k', 'services.livekit.secret' => 's']);
        $admin = $this->signIn();

        $owner = User::create(['name' => 'Scorer', 'email' => 's@haraan.test', 'password' => bcrypt('x'), 'role' => 'USER', 'status' => 'active']);
        $match = LiveMatch::create(['sport' => 'cricket', 'status' => 'live', 'home' => 'A', 'away' => 'B', 'title' => 'A vs B']);
        $match->forceFill(['user_id' => $owner->id])->save();
        $session = MatchCommentarySession::create([
            'match_id' => $match->id, 'user_id' => $owner->id, 'room' => 'match-'.$match->id.'-x',
            'started_at' => now(), 'last_heartbeat_at' => now(),
        ]);

        $this->get('/control/game-hub/live-commentary')->assertOk()->assertSee('Scorer');

        Livewire::test(ListMatchCommentarySessions::class)
            ->callTableAction('take_off_air', $session);

        $session->refresh();
        $this->assertSame('admin', $session->ended_reason);
        $this->assertSame($admin->id, $session->ended_by);
        $this->assertTrue(AdminAction::where('action', 'commentary.taken_off_air')->exists());
    }

    private function signIn(): User
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@haraan.test', 'password' => bcrypt('secret123'), 'role' => 'ADMIN', 'status' => 'active']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('control'));

        return $admin;
    }
}
