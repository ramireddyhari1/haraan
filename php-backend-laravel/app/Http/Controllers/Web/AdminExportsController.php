<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Response;

use App\Traits\LogsAdminActions;

final class AdminExportsController extends Controller
{
    use LogsAdminActions;
    public function bookingsCsv(): Response
    {
        $rows = Booking::with(['user','event'])->orderByDesc('created_at')->get();
        $lines = [];
        $lines[] = 'id,user_id,user_name,event_id,event_title,quantity,total_amount,status,created_at';
        foreach ($rows as $r) {
            $lines[] = implode(',', [
                $r->id,
                $r->user_id,
                '"'.str_replace('"','""', $r->user?->name ?? '').'"',
                $r->event_id,
                '"'.str_replace('"','""', $r->event?->title ?? '').'"',
                $r->quantity,
                $r->total_amount,
                $r->status,
                $r->created_at,
            ]);
        }
        $csv = implode("\n", $lines);
        $this->logAction('export.bookings', ['rows' => count($rows)]);
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="bookings.csv"',
        ]);
    }

    public function paymentsCsv(): Response
    {
        // Reuse bookings list for payments
        return $this->bookingsCsv();
    }

    public function usersCsv(): Response
    {
        $rows = User::orderByDesc('created_at')->get();
        $lines = [];
        $lines[] = 'id,name,email,role,status,created_at';
        foreach ($rows as $r) {
            $lines[] = implode(',', [
                $r->id,
                '"'.str_replace('"','""', $r->name ?? '').'"',
                '"'.str_replace('"','""', $r->email ?? '').'"',
                $r->role,
                $r->status ?? 'ACTIVE',
                $r->created_at,
            ]);
        }
        $csv = implode("\n", $lines);
        $this->logAction('export.users', ['rows' => count($rows)]);
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users.csv"',
        ]);
    }
}
