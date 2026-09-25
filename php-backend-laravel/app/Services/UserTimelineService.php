<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminAction;
use App\Models\Booking;
use App\Models\PlayerMatchStat;
use App\Models\PlayerReport;
use App\Models\ReputationEvent;
use App\Models\RewardGrant;
use App\Models\SupportThread;
use App\Models\User;
use App\Models\UserNote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Enterprise Unified Activity Timeline Service.
 *
 * Synthesizes real historical events across the user's entire lifecycle into
 * a single chronological audit stream:
 *  1. Admin Actions (security, status toggles, credential changes)
 *  2. Bookings (reservations placed, payments confirmed, cancellations)
 *  3. GameHub Activity (match appearances, figures, results)
 *  4. Support Threads (conversations initiated, status transitions)
 *  5. Rewards & Loyalty (grants unlocked, claimed, redeemed)
 *  6. Moderation & Safety (complaints filed, trust penalties levied)
 *  7. Staff Internal Notes (operator scratchpad, warnings)
 */
class UserTimelineService
{
    /**
     * @return Collection<int, array{id: string, occurred_at: Carbon, category: string, category_label: string, badge_color: string, icon: string, title: string, description: string, actor: string, action_url: ?string}>
     */
    public static function getTimelineFor(
        User $user,
        string $category = 'all',
        string $search = '',
        int $limit = 60,
        ?User $viewer = null
    ): Collection {
        $events = collect();

        // 1. Admin Actions
        if ($category === 'all' || $category === 'admin') {
            $actions = AdminAction::query()
                ->where('subject_type', 'User')
                ->where('subject_id', $user->id)
                ->with('user')
                ->latest('created_at')
                ->take($limit)
                ->get();

            foreach ($actions as $act) {
                $color = match (true) {
                    str_contains($act->action, 'suspended'), str_contains($act->action, 'revoked') => 'danger',
                    str_contains($act->action, 'verified'), str_contains($act->action, 'reactivated') => 'success',
                    str_contains($act->action, 'password'), str_contains($act->action, 'pii') => 'warning',
                    default => 'info',
                };

                $events->push([
                    'id'             => "admin_{$act->id}",
                    'occurred_at'    => $act->created_at ?? now(),
                    'category'       => 'admin',
                    'category_label' => 'Security & Admin',
                    'badge_color'    => $color,
                    'icon'           => 'heroicon-m-shield-check',
                    'title'          => ucfirst(str_replace(['user.', '.'], ['', ' '], $act->action)),
                    'description'    => $act->summary(),
                    'actor'          => $act->user?->name ?? 'System',
                    'action_url'     => null,
                ]);
            }
        }

        // 2. Bookings
        if ($category === 'all' || $category === 'booking') {
            $bookings = Booking::query()
                ->where('user_id', $user->id)
                ->with('event')
                ->latest('created_at')
                ->take($limit)
                ->get();

            foreach ($bookings as $b) {
                $statusLower = strtolower((string) $b->status);
                $color = match ($statusLower) {
                    'confirmed', 'paid', 'completed', 'checked_in' => 'success',
                    'cancelled', 'canceled', 'refunded', 'failed' => 'danger',
                    default => 'warning',
                };

                $eventTitle = $b->event?->title ?? ($b->booking_type === 'venue' ? 'Venue slot' : 'Booking');
                $code = $b->ticket_code ? "#" . substr($b->ticket_code, 0, 10) . "…" : "#{$b->id}";

                $events->push([
                    'id'             => "booking_{$b->id}",
                    'occurred_at'    => $b->created_at ?? now(),
                    'category'       => 'booking',
                    'category_label' => 'Booking',
                    'badge_color'    => $color,
                    'icon'           => 'heroicon-m-calendar-days',
                    'title'          => "Booking {$code} · " . ucfirst($statusLower),
                    'description'    => "{$eventTitle} · ₹" . number_format((float) $b->total_amount, 2) . " · Qty: {$b->quantity}",
                    'actor'          => 'Customer',
                    'action_url'     => route('filament.control.events.resources.bookings.index', [
                        'tableFilters' => ['user_id' => ['value' => $user->id]],
                    ]),
                ]);
            }
        }

        // 3. GameHub Match Figures
        if ($category === 'all' || $category === 'match') {
            $stats = PlayerMatchStat::query()
                ->where('user_id', $user->id)
                ->with('match')
                ->latest('created_at')
                ->take($limit)
                ->get();

            foreach ($stats as $s) {
                $played = $s->played ? 'Played' : 'Bench / Squad';
                $figures = "{$s->runs} runs ({$s->balls}b)";
                if ($s->wickets > 0 || (float) $s->overs_bowled > 0) {
                    $figures .= " · {$s->wickets} wkts ({$s->overs_bowled} ov)";
                }

                $matchTitle = $s->match?->title ?? 'Live Match';

                $events->push([
                    'id'             => "match_{$s->id}",
                    'occurred_at'    => $s->created_at ?? now(),
                    'category'       => 'match',
                    'category_label' => 'GameHub',
                    'badge_color'    => $s->played ? 'info' : 'gray',
                    'icon'           => 'heroicon-m-trophy',
                    'title'          => "Match Figures · " . ucfirst($s->sport ?? 'Cricket'),
                    'description'    => "{$matchTitle} · {$figures} [{$played}]",
                    'actor'          => 'Scorer / GameHub',
                    'action_url'     => route('filament.control.game-hub.resources.live-matches.index', [
                        'tableFilters' => ['user_id' => ['value' => $user->id]],
                    ]),
                ]);
            }
        }

        // 4. Support Conversations
        if ($category === 'all' || $category === 'support') {
            $threads = SupportThread::query()
                ->where('user_id', $user->id)
                ->latest('created_at')
                ->take($limit)
                ->get();

            foreach ($threads as $t) {
                $color = match ($t->status) {
                    'open' => 'danger',
                    'pending' => 'warning',
                    'closed' => 'success',
                    default => 'gray',
                };

                $events->push([
                    'id'             => "support_{$t->id}",
                    'occurred_at'    => $t->created_at ?? now(),
                    'category'       => 'support',
                    'category_label' => 'Support',
                    'badge_color'    => $color,
                    'icon'           => 'heroicon-m-chat-bubble-left-right',
                    'title'          => "Support Thread: " . ($t->subject ?: 'General Issue'),
                    'description'    => "Status: " . ucfirst((string) $t->status) . " · Unread: {$t->user_unread_count} user / {$t->admin_unread_count} staff",
                    'actor'          => 'Customer Support',
                    'action_url'     => route('filament.control.resources.support-threads.index', [
                        'tableFilters' => ['user_id' => ['value' => $user->id]],
                    ]),
                ]);
            }
        }

        // 5. Reward Grants
        if ($category === 'all' || $category === 'reward') {
            $rewards = RewardGrant::query()
                ->where('user_id', $user->id)
                ->latest('created_at')
                ->take($limit)
                ->get();

            foreach ($rewards as $r) {
                $color = match ($r->status) {
                    'available', 'claimed', 'redeemed' => 'success',
                    'expired', 'revoked' => 'danger',
                    default => 'info',
                };

                $bonus = $r->bonus_xp ? " · +{$r->bonus_xp} Bonus XP" : '';

                $events->push([
                    'id'             => "reward_{$r->id}",
                    'occurred_at'    => $r->created_at ?? now(),
                    'category'       => 'reward',
                    'category_label' => 'Reward',
                    'badge_color'    => $color,
                    'icon'           => 'heroicon-m-gift',
                    'title'          => "Reward: " . $r->title,
                    'description'    => "Status: " . ucfirst($r->status) . "{$bonus} · " . Str::limit((string) $r->description, 60),
                    'actor'          => 'Reward Engine',
                    'action_url'     => route('filament.control.resources.rewards.reward-grants.index', [
                        'tableFilters' => ['user_id' => ['value' => $user->id]],
                    ]),
                ]);
            }
        }

        // 6. Moderation Complaints Filed Against User
        if ($category === 'all' || $category === 'moderation') {
            $reports = PlayerReport::query()
                ->where('reported_id', $user->id)
                ->with('reporter')
                ->latest('created_at')
                ->take($limit)
                ->get();

            foreach ($reports as $rep) {
                $color = match ($rep->status) {
                    'open' => 'danger',
                    'actioned' => 'warning',
                    default => 'gray',
                };

                $reporterName = $rep->reporter?->name ?? 'Anonymous';

                $events->push([
                    'id'             => "report_{$rep->id}",
                    'occurred_at'    => $rep->created_at ?? now(),
                    'category'       => 'moderation',
                    'category_label' => 'Moderation',
                    'badge_color'    => $color,
                    'icon'           => 'heroicon-m-flag',
                    'title'          => "Reported for: " . ucfirst(str_replace('_', ' ', $rep->reason)),
                    'description'    => "By {$reporterName} · Status: " . ucfirst($rep->status) . " · Detail: " . Str::limit((string) $rep->details, 60),
                    'actor'          => 'Player Report',
                    'action_url'     => route('filament.control.resources.player-reports.index', [
                        'tableFilters' => ['reported_id' => ['value' => $user->id]],
                    ]),
                ]);
            }

            // Reputation penalties
            if ($user->player_id) {
                $penalties = ReputationEvent::query()
                    ->where('player_id', $user->player_id)
                    ->latest('created_at')
                    ->take($limit)
                    ->get();

                foreach ($penalties as $pen) {
                    $events->push([
                        'id'             => "penalty_{$pen->id}",
                        'occurred_at'    => $pen->created_at ?? now(),
                        'category'       => 'moderation',
                        'category_label' => 'Moderation',
                        'badge_color'    => 'danger',
                        'icon'           => 'heroicon-m-shield-exclamation',
                        'title'          => "Trust Penalty: -{$pen->amount} pts",
                        'description'    => "Reason: " . ($pen->reason ?: ucfirst($pen->type)),
                        'actor'          => 'ActionBoard Reputation Engine',
                        'action_url'     => null,
                    ]);
                }
            }
        }

        // 7. Internal Admin Notes
        if ($category === 'all' || $category === 'note') {
            $notesQuery = UserNote::query()
                ->where('user_id', $user->id)
                ->with('author')
                ->latest('created_at')
                ->take($limit);

            if ($viewer !== null) {
                $notesQuery->forOperator($viewer);
            }

            foreach ($notesQuery->get() as $n) {
                $color = match ($n->category) {
                    'risk' => 'danger',
                    'financial' => 'warning',
                    'moderation' => 'danger',
                    'support' => 'info',
                    default => 'primary',
                };

                $authorName = $n->author?->name ?? 'Staff';
                $title = $n->title ?: ucfirst($n->category) . ' note';

                $events->push([
                    'id'             => "note_{$n->id}",
                    'occurred_at'    => $n->created_at ?? now(),
                    'category'       => 'note',
                    'category_label' => 'Staff Note',
                    'badge_color'    => $color,
                    'icon'           => 'heroicon-m-document-text',
                    'title'          => ($n->is_pinned ? '📌 ' : '') . $title,
                    'description'    => Str::limit($n->content, 80),
                    'actor'          => "Note by {$authorName}" . ($n->is_confidential ? ' 🔒 Confidential' : ''),
                    'action_url'     => null,
                ]);
            }
        }

        // Filter by text search if provided
        if (trim($search) !== '') {
            $needle = strtolower(trim($search));
            $events = $events->filter(fn (array $item): bool =>
                str_contains(strtolower($item['title']), $needle)
                || str_contains(strtolower($item['description']), $needle)
                || str_contains(strtolower($item['actor']), $needle)
                || str_contains(strtolower($item['category_label']), $needle)
            );
        }

        // Sort chronologically descending
        return $events->sortByDesc('occurred_at')->take($limit)->values();
    }
}
