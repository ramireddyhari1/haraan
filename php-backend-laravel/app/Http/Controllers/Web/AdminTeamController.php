<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

final class AdminTeamController extends Controller
{
    public function indexJson(Request $request, string $role)
    {
        $role = strtoupper($role);
        $users = User::where('role', $role)->orderByDesc('created_at')->get(['id','name','email','role','status']);
        return response()->json(['data' => $users]);
    }

    public function create(string $role): View
    {
        return view('admin.pages.team-new', ['title' => 'Create '.ucfirst(strtolower($role)), 'role' => strtoupper($role)]);
    }

    public function store(Request $request, string $role)
    {
        $role = strtoupper($role);
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($request->input('password', 'secret')),
            'role' => $role,
            'status' => 'ACTIVE',
        ]);

        return response()->json(['message' => ucfirst(strtolower($role)).' created', 'data' => $user], 201);
    }

    public function edit(string $role, string $id): View
    {
        return view('admin.pages.team-edit', ['title' => 'Edit '.ucfirst(strtolower($role)), 'role' => strtoupper($role), 'id' => $id]);
    }

    public function update(Request $request, string $role, string $id)
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email'],
            'status' => ['nullable','string'],
        ]);

        $user->fill(['name' => $data['name'], 'email' => $data['email'], 'status' => $data['status'] ?? $user->status]);
        if ($request->filled('password')) {
            $user->password = bcrypt($request->input('password'));
        }
        $user->save();

        return response()->json(['message' => 'User updated', 'data' => $user]);
    }

    public function destroy(string $role, string $id)
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }
}
