<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

use App\Traits\LogsAdminActions;

final class AdminPayoutsController extends Controller
{
    use LogsAdminActions;
    public function indexJson(Request $request): JsonResponse
    {
        $q = $request->query('q');
        $query = Payout::query()->with(['booking','booking.user','booking.event'])->orderByDesc('created_at');

        if ($q) {
            $query->where(function($r) use ($q) {
                $r->where('payouts.id', 'like', "%{$q}%")->orWhereHas('booking', function($b) use ($q) {
                    $b->where('id', 'like', "%{$q}%")->orWhereHas('user', function($u) use ($q) {
                        $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
                    });
                });
            });
        }

        $limit = (int) $request->query('limit', 50);
        $data = $query->paginate($limit);
        return response()->json(['data' => $data]);
    }

    public function create(Request $request): JsonResponse
    {
        $bookingId = $request->input('booking_id');
        $booking = Booking::find($bookingId);
        if (! $booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }
        $payout = Payout::create([ 'booking_id' => $booking->id, 'amount' => $booking->total_amount, 'status' => 'PENDING' ]);
        $this->logAction('payout.create', ['payout_id' => $payout->id, 'booking_id' => $booking->id]);
        return response()->json(['message' => 'Payout created', 'data' => $payout], 201);
    }

    public function process(Request $request, string $id): JsonResponse
    {
        $payout = Payout::find($id);
        if (! $payout) {
            return response()->json(['error' => 'Payout not found'], 404);
        }
        $payout->status = 'PAID';
        $payout->processed_at = Carbon::now();
        $payout->save();
        $this->logAction('payout.process', ['payout_id' => $payout->id]);
        return response()->json(['message' => 'Payout processed', 'data' => $payout]);
    }
}
