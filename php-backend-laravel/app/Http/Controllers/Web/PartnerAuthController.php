<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\View\View;

final class PartnerAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('partner.pages.login');
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
            if ($role !== 'PARTNER') {
                Auth::logout();
                return back()->withErrors(['email' => 'Access denied for this account.']);
            }

            return redirect()->intended(route('partner.dashboard'));
        }

        return back()->withErrors(['email' => 'Invalid credentials']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('partner.login');
    }
}
