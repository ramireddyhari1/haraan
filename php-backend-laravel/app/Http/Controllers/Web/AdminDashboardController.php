<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class AdminDashboardController extends Controller
{
    public function home(): View
    {
        return view('admin.pages.home', [
            'title' => 'Admin Command Center',
            'stats' => [
                ['label' => 'Event Tracks', 'value' => '18', 'note' => 'Singing, dance, quiz, cultural, sports'],
                ['label' => 'GameHub Leagues', 'value' => '24', 'note' => 'Tournaments, live scoring, brackets'],
                ['label' => 'Co-admins', 'value' => '8', 'note' => 'Area-specific moderators and reviewers'],
                ['label' => 'Workers', 'value' => '64', 'note' => 'Volunteers, scorekeepers, floor staff'],
            ],
            'modules' => [
                [
                    'title' => 'Events Management',
                    'description' => 'Create and control event categories separately for singing, dance, stage acts, sports, and local competitions.',
                    'link' => route('admin.events'),
                ],
                [
                    'title' => 'GameHub Management',
                    'description' => 'Manage match boards, live scores, fixtures, leaderboards, and tournament metadata in a separate lane.',
                    'link' => route('admin.gamehub'),
                ],
                [
                    'title' => 'Partner Oversight',
                    'description' => 'Approve partner accounts, assign scopes, and monitor which partner owns which event or GameHub program.',
                    'link' => route('admin.partners'),
                ],
                [
                    'title' => 'Team Access',
                    'description' => 'Control co-admin permissions, workers, and operational users without giving full platform ownership.',
                    'link' => route('admin.workers'),
                ],
            ],
        ]);
    }

    public function login(): View
    {
        return view('admin.pages.login', ['title' => 'Admin Login']);
    }

    public function bookings(): View
    {
        return view('admin.pages.bookings', ['title' => 'Bookings']);
    }

    public function coupons(): View
    {
        return view('admin.pages.coupons', ['title' => 'Coupons']);
    }

    public function events(): View
    {
        return view('admin.pages.events', ['title' => 'Events']);
    }

    public function eventsNew(): View
    {
        return view('admin.pages.events-new', ['title' => 'Create Event']);
    }

    public function gamehub(): View
    {
        return view('admin.pages.gamehub', [
            'title' => 'GameHub Management',
            'lanes' => [
                ['name' => 'Tournament setup', 'detail' => 'Create league, knockout, and custom brackets for game hub events.'],
                ['name' => 'Live scoring', 'detail' => 'Track scorecards, innings, rounds, and live dashboards in one place.'],
                ['name' => 'Leaderboard sync', 'detail' => 'Publish results and rankings to the public app and partner portal.'],
            ],
        ]);
    }

    public function partners(): View
    {
        return view('admin.pages.partners', [
            'title' => 'Partners',
            'partners' => [
                ['name' => 'Sunrise Events', 'scope' => 'Events', 'status' => 'Active'],
                ['name' => 'Arena Sports Club', 'scope' => 'GameHub', 'status' => 'Active'],
                ['name' => 'Rhythm House', 'scope' => 'Events', 'status' => 'Pending review'],
            ],
        ]);
    }

    public function coAdmins(): View
    {
        return view('admin.pages.co-admins', [
            'title' => 'Co-admins',
            'team' => [
                ['name' => 'Asha', 'scope' => 'Events', 'access' => 'Moderation + approvals'],
                ['name' => 'Farhan', 'scope' => 'GameHub', 'access' => 'Fixtures + live scoring'],
                ['name' => 'Meera', 'scope' => 'Partners', 'access' => 'Onboarding + verification'],
            ],
        ]);
    }

    public function workers(): View
    {
        return view('admin.pages.workers', [
            'title' => 'Workers',
            'workers' => [
                ['name' => 'Floor team', 'work' => 'Venue coordination, check-in, crowd support'],
                ['name' => 'Scorekeepers', 'work' => 'Event scoring and GameHub data updates'],
                ['name' => 'Support staff', 'work' => 'Helpdesk, registrations, issue resolution'],
            ],
        ]);
    }

    public function payments(): View
    {
        return view('admin.pages.payments', ['title' => 'Payments']);
    }

    public function payouts(): View
    {
        return view('admin.pages.payouts', ['title' => 'Payout Requests']);
    }

    public function scan(): View
    {
        return view('admin.pages.scan', ['title' => 'Scan QR']);
    }

    public function settings(): View
    {
        return view('admin.pages.settings', ['title' => 'Settings']);
    }

    public function users(): View
    {
        return view('admin.pages.users', ['title' => 'Users']);
    }

    public function withdraw(): View
    {
        return view('admin.pages.withdraw', ['title' => 'Withdraw']);
    }
}
