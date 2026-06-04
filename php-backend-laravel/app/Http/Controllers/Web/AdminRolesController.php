<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

final class AdminRolesController extends Controller
{
    public function index(): View
    {
        return view('admin.pages.roles', ['title' => 'Roles & Permissions']);
    }

    public function indexJson(Request $request)
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        return response()->json(['data' => $roles]);
    }

    public function permissionsJson()
    {
        $perms = Permission::orderBy('name')->get();
        return response()->json(['data' => $perms]);
    }

    public function storePermission(Request $request)
    {
        $request->validate(['name' => ['required','string','max:100']]);
        $perm = Permission::create(['name' => $request->input('name')]);
        return response()->json(['message' => 'Permission created', 'data' => $perm], 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate(['permissions' => ['nullable','array']]);
        $role = Role::findOrFail($id);
        $perms = $request->input('permissions', []);
        $role->syncPermissions($perms);
        $role->load('permissions');
        return response()->json(['message' => 'Role updated', 'data' => $role]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => ['required','string','max:100']]);
        $role = Role::create(['name' => $request->input('name')]);
        $perms = $request->input('permissions', []);
        if (! empty($perms)) {
            $role->syncPermissions($perms);
        }
        return response()->json(['message' => 'Role created', 'data' => $role], 201);
    }
}
