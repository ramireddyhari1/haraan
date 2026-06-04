<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WhatsAppAuthController extends Controller
{
    protected WhatsAppService $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Request OTP via WhatsApp.
     */
    public function requestOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $phone = preg_replace('/[^0-9]/', '', $request->phone);

        // Find or create user
        $user = User::where('phone', $phone)->first();
        if (!$user) {
            // Auto-create account for new users
            $user = User::create([
                'name' => 'User ' . substr($phone, -4),
                'email' => $phone . '@whatsapp.local', // Dummy email as it is required
                'phone' => $phone,
                'password' => Hash::make(Str::random(16)),
                'role' => 'user',
                'status' => 'active',
            ]);
        }

        // Generate 6-digit OTP
        $otp = rand(100000, 999999);
        
        // Store OTP in session (expires in 5 mins)
        session(['whatsapp_otp' => $otp]);
        session(['whatsapp_phone' => $phone]);
        session(['whatsapp_otp_expires_at' => now()->addMinutes(5)]);

        // Send OTP via WhatsApp
        $message = "Your Haraan login code is: *{$otp}*\n\nThis code will expire in 5 minutes.";
        $sent = $this->whatsappService->sendMessage($phone, $message);

        if ($sent) {
            return back()->with('success', 'OTP sent to your WhatsApp!');
        }

        return back()->with('error', 'Failed to send OTP. Please check if the number is registered on WhatsApp.');
    }

    /**
     * Show OTP Verification form.
     */
    public function showVerifyForm()
    {
        if (!session()->has('whatsapp_phone')) {
            return redirect()->route('login');
        }

        return view('site.auth.verify-otp');
    }

    /**
     * Verify OTP and Login.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|numeric',
        ]);

        $sessionOtp = session('whatsapp_otp');
        $expiresAt = session('whatsapp_otp_expires_at');
        $phone = session('whatsapp_phone');

        if (!$sessionOtp || !$expiresAt || !$phone) {
            return redirect()->route('login')->with('error', 'Session expired. Please try again.');
        }

        if (now()->greaterThan($expiresAt)) {
            session()->forget(['whatsapp_otp', 'whatsapp_otp_expires_at', 'whatsapp_phone']);
            return redirect()->route('login')->with('error', 'OTP has expired. Please request a new one.');
        }

        if ((int)$request->otp !== (int)$sessionOtp) {
            return back()->with('error', 'Invalid OTP. Please try again.');
        }

        // OTP is valid, login the user
        $user = User::where('phone', $phone)->first();
        if ($user) {
            Auth::login($user, true); // login and remember
            session()->forget(['whatsapp_otp', 'whatsapp_otp_expires_at', 'whatsapp_phone']);
            
            if (empty($user->district) || empty($user->state)) {
                return redirect()->route('site.profile.setup')->with('info', 'Please complete your cricket identity registration.');
            }

            return redirect('/')->with('success', 'Logged in successfully via WhatsApp!');
        }

        return redirect()->route('login')->with('error', 'User not found.');
    }

    /**
     * Cancel OTP flow.
     */
    public function cancel()
    {
        session()->forget(['whatsapp_otp', 'whatsapp_otp_expires_at', 'whatsapp_phone']);
        return back();
    }
}
