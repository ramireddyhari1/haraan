<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Coupon;
use Illuminate\Support\Facades\Cache;

use App\Traits\LogsAdminActions;

final class AdminCouponsController extends Controller
{
    use LogsAdminActions;
    private function storageKey(): string
    {
        return 'admin_coupons_v1';
    }

    public function indexJson(Request $request): JsonResponse
    {
        $q = $request->query('q');
        $query = Coupon::query()->orderByDesc('created_at');
        if ($q) {
            $query->where(function($r) use ($q) {
                $r->where('code', 'like', "%{$q}%");
            });
        }
        $limit = (int) $request->query('limit', 50);
        $data = $query->paginate($limit);
        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required','string'],
            'discount' => ['required','numeric'],
            'max_uses' => ['nullable','integer'],
        ]);
        $coupon = Coupon::create(array_merge($data, ['uses' => 0, 'active' => true]));
        $this->logAction('coupon.create', ['id' => $coupon->id, 'code' => $coupon->code]);
        return response()->json(['message' => 'Coupon created', 'data' => $coupon], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $coupon = Coupon::find($id);
        if (! $coupon) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $coupon->fill($request->only(['code','discount','max_uses','active']));
        $coupon->save();
        $this->logAction('coupon.update', ['id' => $coupon->id]);
        return response()->json(['message' => 'Updated', 'data' => $coupon]);
    }

    public function destroy(string $id): JsonResponse
    {
        $coupon = Coupon::find($id);
        if (! $coupon) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $coupon->delete();
        $this->logAction('coupon.delete', ['id' => $id]);
        return response()->json(['message' => 'Deleted']);
    }
}
