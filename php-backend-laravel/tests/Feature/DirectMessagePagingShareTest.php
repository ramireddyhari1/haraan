<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\InboxUpdated;
use App\Models\Conversation;
use App\Models\LiveMatch;
use App\Models\User;
use App\Support\PlatformRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Chat: loading older messages, the live inbox signal, and shared match / player cards.
 */
class DirectMessagePagingShareTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function player(string $name): User
    {
        $this->seq++;

        return User::create([
            'name' => $name, 'email' => strtolower($name) . '@haraan.test',
            'password' => Hash::make('secret123'), 'role' => 'user', 'status' => 'active',
            'player_id' => 'HRN' . str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT),
            'is_guest' => false,
            'state' => 'Andhra Pradesh', 'district' => 'YSR Kadapa',
            'primary_sport' => 'Cricket',
            'sport_attributes' => ['role' => 'All-rounder', 'batting' => 'Right', 'bowling' => 'Right-arm medium'],
        ]);
    }

    private function as(User $u): \Illuminate\Testing\TestCase|\Tests\TestCase
    {
        $token = \App\Support\JwtService::issue(
            ['sub' => $u->id],
            (string) config('app.jwt_secret', env('JWT_SECRET', 'change_me')),
        );

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    /** @return array{0: User, 1: User, 2: int} */
    private function pair(): array
    {
        $a = $this->player('Ava');
        $b = $this->player('Ben');
        $a->follow($b);
        $b->follow($a);
        $id = (int) $this->as($a)->postJson('/api/dm/with/' . $b->player_id)->json('id');

        return [$a, $b, $id];
    }

    public function test_a_page_is_the_newest_messages_and_before_id_walks_back(): void
    {
        [$a, , $id] = $this->pair();
        for ($i = 1; $i <= 7; $i++) {
            $this->as($a)->postJson("/api/dm/{$id}/messages", ['body' => "m{$i}"])->assertCreated();
        }

        $first = $this->as($a)->getJson("/api/dm/{$id}/messages?limit=3")->assertOk();
        $this->assertSame(['m5', 'm6', 'm7'], array_column($first->json('results'), 'body'));
        $this->assertTrue($first->json('has_more'));

        $oldest = $first->json('results.0.id');
        $second = $this->as($a)->getJson("/api/dm/{$id}/messages?limit=3&before_id={$oldest}")->assertOk();
        $this->assertSame(['m2', 'm3', 'm4'], array_column($second->json('results'), 'body'));
        $this->assertTrue($second->json('has_more'));

        $oldest = $second->json('results.0.id');
        $last = $this->as($a)->getJson("/api/dm/{$id}/messages?limit=3&before_id={$oldest}")->assertOk();
        $this->assertSame(['m1'], array_column($last->json('results'), 'body'));
        $this->assertFalse($last->json('has_more'));
    }

    /** An old build sends no limit: it must get the LATEST messages, in reading order. */
    public function test_no_limit_still_returns_the_latest_in_reading_order(): void
    {
        [$a, , $id] = $this->pair();
        foreach (['one', 'two', 'three'] as $body) {
            $this->as($a)->postJson("/api/dm/{$id}/messages", ['body' => $body]);
        }

        $this->assertSame(
            ['one', 'two', 'three'],
            array_column($this->as($a)->getJson("/api/dm/{$id}/messages")->json('results'), 'body'),
        );
    }

    public function test_the_limit_is_capped_by_the_control_setting(): void
    {
        PlatformRules::save(['chat.page_size' => 20]);
        [$a, , $id] = $this->pair();
        for ($i = 1; $i <= 25; $i++) {
            $this->as($a)->postJson("/api/dm/{$id}/messages", ['body' => "m{$i}"]);
        }

        $this->assertCount(20, $this->as($a)->getJson("/api/dm/{$id}/messages?limit=200")->json('results'));
    }

    public function test_each_player_gets_their_own_unguessable_inbox_channel(): void
    {
        [$a, $b] = $this->pair();

        $ca = $this->as($a)->getJson('/api/dm')->json('inbox_channel');
        $cb = $this->as($b)->getJson('/api/dm')->json('inbox_channel');

        $this->assertStringStartsWith('inbox.', $ca);
        $this->assertNotSame($ca, $cb);
        // A keyed hash, not "inbox.{id}": nobody can listen in by guessing a user id.
        $this->assertNotSame('inbox.' . $a->id, $ca);
        $this->assertSame(32, strlen(substr($ca, strlen('inbox.'))));
    }

    public function test_a_send_nudges_every_members_inbox(): void
    {
        [$a, $b, $id] = $this->pair();
        Event::fake([InboxUpdated::class]);

        $this->as($a)->postJson("/api/dm/{$id}/messages", ['body' => 'hi'])->assertCreated();

        $expected = [
            \App\Services\DirectMessageService::inboxChannel($a),
            \App\Services\DirectMessageService::inboxChannel($b),
        ];
        Event::assertDispatched(InboxUpdated::class, function (InboxUpdated $e) use ($expected): bool {
            $got = $e->channels;
            sort($got);
            sort($expected);

            return $got === $expected;
        });
    }

    public function test_the_live_inbox_switch_turns_the_channel_off(): void
    {
        PlatformRules::save(['chat.live_inbox' => false]);
        [$a, , $id] = $this->pair();
        Event::fake([InboxUpdated::class]);

        $this->assertNull($this->as($a)->getJson('/api/dm')->json('inbox_channel'));
        $this->as($a)->postJson("/api/dm/{$id}/messages", ['body' => 'hi']);
        Event::assertNotDispatched(InboxUpdated::class);
    }

    public function test_a_shared_public_match_arrives_as_a_card_with_the_live_score(): void
    {
        [$a, $b, $id] = $this->pair();
        $match = LiveMatch::create([
            'home' => 'Lions', 'away' => 'Tigers', 'status' => 'Live', 'sport' => 'cricket',
            'score_text' => '86/3', 'overs' => '9.4', 'is_private' => false, 'user_id' => $a->id,
        ]);

        $this->as($a)->postJson("/api/dm/{$id}/messages", ['share' => ['type' => 'match', 'id' => (string) $match->id]])
            ->assertCreated()
            ->assertJsonPath('share.title', 'Lions vs Tigers');

        $card = $this->as($b)->getJson("/api/dm/{$id}/messages")->json('results.0.share');
        $this->assertTrue($card['available']);
        $this->assertSame('LIVE', $card['badge']);
        $this->assertSame('86/3 (9.4 ov)', $card['line']);

        // The thread list says what was shared, not a blank line.
        $this->assertSame(
            'Shared a match: Lions vs Tigers',
            $this->as($b)->getJson('/api/dm')->json('results.0.last_message'),
        );

        // The score moves; the card reads it fresh.
        $match->update(['score_text' => '102/4', 'overs' => '11.2']);
        $this->as($b)->getJson('/api/dm/share/card?type=match&id=' . $match->id)
            ->assertOk()
            ->assertJsonPath('line', '102/4 (11.2 ov)');
    }

    /** A private match the reader is not in shows as a closed card, never its details. */
    public function test_a_private_match_card_hides_its_details_from_outsiders(): void
    {
        [$a, $b, $id] = $this->pair();
        $match = LiveMatch::create([
            'home' => 'Secret XI', 'away' => 'Hidden XI', 'status' => 'Live', 'sport' => 'cricket',
            'is_private' => true, 'user_id' => $a->id,
        ]);

        $this->as($a)->postJson("/api/dm/{$id}/messages", ['share' => ['type' => 'match', 'id' => (string) $match->id]])
            ->assertCreated();

        $card = $this->as($b)->getJson("/api/dm/{$id}/messages")->json('results.0.share');
        $this->assertFalse($card['available']);
        $this->assertSame('Private match', $card['title']);
        $this->assertStringNotContainsString('Secret', json_encode($card));
    }

    /** You cannot share what you cannot see — a guessed private match id is refused. */
    public function test_a_player_cannot_share_a_private_match_they_are_not_in(): void
    {
        [$a, , $id] = $this->pair();
        $owner = $this->player('Cal');
        $match = LiveMatch::create([
            'home' => 'X', 'away' => 'Y', 'status' => 'Live', 'is_private' => true, 'user_id' => $owner->id,
        ]);

        $this->as($a)->postJson("/api/dm/{$id}/messages", ['share' => ['type' => 'match', 'id' => (string) $match->id]])
            ->assertStatus(422);
    }

    public function test_a_player_card_and_the_picker(): void
    {
        [$a, $b, $id] = $this->pair();

        // With no search, the picker offers mutual follows.
        $this->as($a)->getJson('/api/dm/share/search?type=player')
            ->assertOk()
            ->assertJsonPath('results.0.id', $b->player_id);

        $this->as($a)->postJson("/api/dm/{$id}/messages", [
            'body' => 'Get him in the team',
            'share' => ['type' => 'player', 'id' => $b->player_id],
        ])->assertCreated();

        $row = $this->as($b)->getJson("/api/dm/{$id}/messages")->json('results.0');
        $this->assertSame('Get him in the team', $row['body']);
        $this->assertSame('Ben', $row['share']['title']);
    }

    public function test_sharing_can_be_switched_off_in_control(): void
    {
        PlatformRules::save(['chat.share_cards' => false]);
        [$a, $b, $id] = $this->pair();

        $this->assertFalse($this->as($a)->getJson('/api/dm')->json('share_cards'));
        $this->as($a)->postJson("/api/dm/{$id}/messages", ['share' => ['type' => 'player', 'id' => $b->player_id]])
            ->assertForbidden();
    }

    public function test_a_message_needs_words_or_a_card(): void
    {
        [$a, , $id] = $this->pair();

        $this->as($a)->postJson("/api/dm/{$id}/messages", ['body' => '   '])->assertStatus(422);
    }

    public function test_typing_reaches_the_thread_and_the_other_inbox_but_carries_no_words(): void
    {
        [$a, $b, $id] = $this->pair();
        Event::fake([\App\Events\ConversationTyping::class]);

        $this->as($a)->postJson("/api/dm/{$id}/typing", ['body' => 'secret draft'])
            ->assertStatus(202)
            ->assertJsonPath('sent', true);

        Event::assertDispatched(\App\Events\ConversationTyping::class, function ($e) use ($a, $b, $id): bool {
            $names = array_map(fn ($c) => $c->name, $e->broadcastOn());

            return $e->senderId === $a->id
                && in_array('conversation.' . $id, $names, true)
                && in_array(\App\Services\DirectMessageService::inboxChannel($b), $names, true)
                // The typist's own inbox is not nudged.
                && ! in_array(\App\Services\DirectMessageService::inboxChannel($a), $names, true)
                && ! str_contains(json_encode($e->broadcastWith()), 'secret');
        });
    }

    public function test_typing_is_throttled_and_can_be_switched_off(): void
    {
        [$a, , $id] = $this->pair();
        Event::fake([\App\Events\ConversationTyping::class]);

        $this->as($a)->postJson("/api/dm/{$id}/typing")->assertJsonPath('sent', true);
        // A second ping inside 3 seconds is dropped.
        $this->as($a)->postJson("/api/dm/{$id}/typing")->assertJsonPath('sent', false);
        Event::assertDispatchedTimes(\App\Events\ConversationTyping::class, 1);

        PlatformRules::save(['chat.typing_indicator' => false]);
        \Illuminate\Support\Facades\Cache::flush();
        $this->as($a)->postJson("/api/dm/{$id}/typing")->assertJsonPath('sent', false);
        $this->assertFalse($this->as($a)->getJson("/api/dm/{$id}/messages")->json('typing_indicator'));
    }

    public function test_an_outsider_cannot_send_typing(): void
    {
        [, , $id] = $this->pair();
        $stranger = $this->player('Dev');

        $this->as($stranger)->postJson("/api/dm/{$id}/typing")->assertNotFound();
    }

    public function test_forwarding_keeps_the_card(): void
    {
        [$a, $b, $id] = $this->pair();
        $c = $this->player('Cal');
        $a->follow($c);
        $c->follow($a);
        $other = (int) $this->as($a)->postJson('/api/dm/with/' . $c->player_id)->json('id');

        $sent = $this->as($a)->postJson("/api/dm/{$id}/messages", ['share' => ['type' => 'player', 'id' => $b->player_id]])
            ->json('id');
        $this->as($a)->postJson("/api/dm/messages/{$sent}/forward", ['to' => $other])->assertCreated();

        $this->assertSame('Ben', $this->as($c)->getJson("/api/dm/{$other}/messages")->json('results.0.share.title'));
        $this->assertSame(1, Conversation::query()->whereKey($other)->count());
    }
}
