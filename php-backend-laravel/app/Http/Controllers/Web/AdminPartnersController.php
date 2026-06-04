<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

final class AdminPartnersController extends Controller
{
    public function indexJson()
    {
        $partners = User::where('role', 'PARTNER')->orderByDesc('created_at')->get(['id', 'name', 'email', 'status', 'partner_type']);
        return response()->json(['data' => $partners]);
    }

    public function create(): View
    {
        return view('admin.pages.partners-new', ['title' => 'Create Partner']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'partner_type' => ['nullable', 'string'],
        ]);

        $partner = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($request->input('password', 'partner123')),
            'role' => 'PARTNER',
            'status' => 'PENDING',
            'partner_type' => $data['partner_type'] ?? null,
        ]);

        return response()->json(['message' => 'Partner created', 'data' => $partner], 201);
    }

    public function edit(string $id): \Illuminate\Contracts\View\View
    {
        return view('admin.pages.partners-edit', ['title' => 'Edit Partner', 'id' => $id]);
    }

    public function update(Request $request, string $id)
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['error' => 'Partner not found'], 404);
        }

        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email'],
            'partner_type' => ['nullable','string'],
            'status' => ['nullable','string'],
        ]);

        $user->fill([ 'name' => $data['name'], 'email' => $data['email'], 'partner_type' => $data['partner_type'] ?? $user->partner_type, 'status' => $data['status'] ?? $user->status ]);
        if ($request->filled('password')) {
            $user->password = bcrypt($request->input('password'));
        }
        $user->save();

        return response()->json(['message' => 'Partner updated', 'data' => $user]);
    }

    public function destroy(string $id)
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['error' => 'Partner not found'], 404);
        }
        $user->delete();
        return response()->json(['message' => 'Partner deleted']);
    }
}
