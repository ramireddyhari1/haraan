<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

final class AdminAuditController extends Controller
{
    public function index(): View
    {
        return view('admin.pages.audit', ['title' => 'Admin Audit Log']);
    }

    public function indexJson(Request $request)
    {
        $limit = (int) $request->query('limit', 50);
        $query = DB::table('admin_actions')->orderByDesc('created_at');
        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where('action', 'like', "%{$q}%")->orWhere('meta', 'like', "%{$q}%");
        }
        $data = $query->paginate($limit);
        return response()->json(['data' => $data]);
    }
}
