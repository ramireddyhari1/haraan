<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class PartnerDashboardController extends Controller
{
    public function home(): View
    {
        return view('partner.pages.home', [
            'title' => 'Partner Dashboard',
            'modules' => [
                [
                    'title' => 'Events lane',
                    'description' => 'Manage singing, dance, cultural, and festival events without mixing them with GameHub operations.',
                    'link' => route('partner.events'),
                ],
                [
                    'title' => 'GameHub lane',
                    'description' => 'Maintain sports fixtures, brackets, scoreboards, and leaderboard updates independently from events.',
                    'link' => route('partner.gamehub'),
                ],
                [
                    'title' => 'Worker assignments',
                    'description' => 'Track hosts, volunteers, referees, and scorekeepers assigned to your partner account.',
                    'link' => route('partner.workers'),
                ],
            ],
        ]);
    }

    public function login(): View
    {
        return view('partner.pages.login', ['title' => 'Partner Login']);
    }

    public function events(): View
    {
        return view('partner.pages.events', [
            'title' => 'Events',
            'tracks' => [
                ['name' => 'Singing', 'detail' => 'Contest rounds, audience flow, and stage schedule.'],
                ['name' => 'Dance', 'detail' => 'Crew registration, slots, and judging setup.'],
                ['name' => 'Local sports', 'detail' => 'Tournament-style events tied to venue and workers.'],
            ],
        ]);
    }

    public function gamehub(): View
    {
        return view('partner.pages.gamehub', [
            'title' => 'GameHub',
            'tracks' => [
                ['name' => 'Match setup', 'detail' => 'Create fixtures, squads, and scorecard readiness.'],
                ['name' => 'Live scoring', 'detail' => 'Update runs, points, and status without touching event data.'],
                ['name' => 'Results', 'detail' => 'Publish leaderboard and match history separately for partners.'],
            ],
        ]);
    }

    public function workers(): View
    {
        return view('partner.pages.workers', [
            'title' => 'Workers',
            'workers' => [
                ['name' => 'Event host', 'detail' => 'Opening ceremony, registrations, announcements.'],
                ['name' => 'Scorekeeper', 'detail' => 'Match updates, leaderboard entries, final results.'],
                ['name' => 'Volunteer crew', 'detail' => 'Crowd flow, seating, logistics, on-ground support.'],
            ],
        ]);
    }
}