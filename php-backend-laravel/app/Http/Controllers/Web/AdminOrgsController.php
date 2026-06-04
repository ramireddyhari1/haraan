<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\OrganizationUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminOrgsController extends Controller
{
    public function index()
    {
        return view('admin.pages.organizations', ['title' => 'Organization Units']);
    }

    public function indexJson()
    {
        $orgs = OrganizationUnit::orderBy('name')->get();
        $maps = DB::table('user_organization_map')->get();
        return response()->json(['data' => $orgs, 'map' => $maps]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => ['required','string','max:255']]);
        $org = OrganizationUnit::create(['name' => $request->input('name'), 'type' => $request->input('type','UNKNOWN'), 'active' => 1]);
        return response()->json(['message' => 'Organization created', 'data' => $org], 201);
    }

    public function assignUser(Request $request, $id)
    {
        $request->validate(['user_id' => ['required','integer']]);
        $userId = (int) $request->input('user_id');
        DB::table('user_organization_map')->updateOrInsert(['user_id' => $userId], ['organization_id' => $id, 'user_id' => $userId]);
        return response()->json(['message' => 'Assigned']);
    }
}
