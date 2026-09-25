<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserAdminResource;
use App\Models\Booking;
use App\Models\User;
use App\Traits\LogsAdminActions;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminExportsController extends Controller
{
    use LogsAdminActions;

    public function bookingsCsv(): StreamedResponse
    {
        $this->logAction('export.bookings', ['type' => 'csv_stream']);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="bookings.csv"',
        ];

        return response()->stream(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'user_id', 'user_name', 'event_id', 'event_title', 'quantity', 'total_amount', 'status', 'created_at']);

            Booking::query()
                ->with(['user:id,name', 'event:id,title'])
                ->orderByDesc('created_at')
                ->chunk(500, function ($bookings) use ($handle): void {
                    foreach ($bookings as $r) {
                        fputcsv($handle, [
                            $r->id,
                            $r->user_id,
                            $r->user?->name ?? '',
                            $r->event_id,
                            $r->event?->title ?? '',
                            $r->quantity,
                            $r->total_amount,
                            $r->status,
                            $r->created_at?->toIso8601String() ?? '',
                        ]);
                    }
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                });

            fclose($handle);
        }, 200, $headers);
    }

    public function paymentsCsv(): StreamedResponse
    {
        // Reuse bookings list for payments
        return $this->bookingsCsv();
    }

    public function usersCsv(): StreamedResponse
    {
        $actor = Auth::user();
        $canViewPii = $actor !== null && (
            $actor->isSuperAdmin()
            || (method_exists($actor, 'can') && $actor->can('users.pii.view'))
        );

        $this->logAction('export.users', ['type' => 'csv_stream', 'unmasked' => $canViewPii]);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users.csv"',
        ];

        return response()->stream(function () use ($canViewPii): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'name', 'email', 'phone', 'role', 'status', 'created_at']);

            User::query()
                ->select(['id', 'name', 'email', 'phone', 'role', 'status', 'created_at'])
                ->orderByDesc('created_at')
                ->chunk(500, function ($users) use ($handle, $canViewPii): void {
                    foreach ($users as $r) {
                        $email = $canViewPii ? $r->email : UserAdminResource::maskEmail($r->email);
                        $phone = $canViewPii ? $r->phone : UserAdminResource::maskPhone($r->phone);

                        fputcsv($handle, [
                            $r->id,
                            $r->name ?? '',
                            $email ?? '',
                            $phone ?? '',
                            $r->role ?? 'USER',
                            $r->status ?? 'ACTIVE',
                            $r->created_at?->toIso8601String() ?? '',
                        ]);
                    }
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                });

            fclose($handle);
        }, 200, $headers);
    }
}
