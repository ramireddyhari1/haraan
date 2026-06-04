<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\View\View;

final class AdminAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('admin.pages.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            $role = strtoupper($user->role ?? '');
            if (! in_array($role, ['ADMIN', 'COADMIN'], true)) {
                Auth::logout();
                return back()->withErrors(['email' => 'Access denied for this account.']);
            }

            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors(['email' => 'Invalid credentials']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }

    /**
     * One-time admin creation helper (ERP key protected route).
     * Usage: /erp/setup-admin?email=admin@x.com&password=secret
     */
    public function setupAdmin(Request $request)
    {
        $email = $request->query('email', 'admin@local');
        $password = $request->query('password', 'password');

        $userModel = \App\Models\User::where('email', $email)->first();
        if ($userModel) {
            return response()->json(['message' => 'Admin already exists', 'email' => $email]);
        }

        $user = \App\Models\User::create([
            'name' => 'Initial Admin',
            'email' => $email,
            'password' => bcrypt($password),
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);

        return response()->json(['message' => 'Admin created', 'email' => $user->email]);
    }
}
