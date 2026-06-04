<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Traits\LogsAdminActions;

final class AdminPaymentsController extends Controller
{
    use LogsAdminActions;
    public function indexJson(Request $request): JsonResponse
    {
        // Treat bookings with status PAID as payments for admin view
        $user = Auth::user();

        $q = $request->query('q');
        if ($user->hasRole('SUPER ADMIN') || $user->can('payments.view.all')) {
            $query = Booking::query()->with(['user','event'])->orderByDesc('created_at');
        } else {
            $orgIds = DB::table('user_organization_map')->where('user_id', $user->id)->pluck('organization_id')->toArray();
            if (empty($orgIds)) {
                return response()->json(['data' => []]);
            }

            $query = Booking::join('events', 'bookings.event_id', '=', 'events.id')
                ->join('user_organization_map as uom', 'uom.user_id', '=', 'events.partner_id')
                ->whereIn('uom.organization_id', $orgIds)
                ->select('bookings.*')
                ->with(['user','event'])
                ->orderByDesc('bookings.created_at');
        }

        if ($request->filled('status') && $request->query('status') !== 'All') {
            $query->where('status', (string) $request->query('status'));
        }

        if ($q) {
            $query->where(function($r) use ($q) {
                $r->where('bookings.id', 'like', "%{$q}%")->orWhere('bookings.status', 'like', "%{$q}%");
            });
        }

        $limit = (int) $request->query('limit', 50);
        $data = $query->paginate($limit);
        return response()->json(['data' => $data]);
    }

    public function markPaid(Request $request, string $id): JsonResponse
    {
        $booking = Booking::find($id);
        if (! $booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }
        $booking->status = 'PAID';
        $booking->save();
        $this->logAction('payment.mark_paid', ['booking_id' => $booking->id]);
        return response()->json(['message' => 'Marked as paid', 'data' => $booking]);
    }
}
