@extends('site.layout')
@section('content')
<section class="auth-shell">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Verify OTP</h1>
            <p>Enter the 6-digit code sent to your WhatsApp.</p>
        </div>

        @if(session('success'))
            <div style="background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px;">
                {{ session('success') }}
            </div>
        @endif
        
        @if(session('error'))
            <div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 20px;">
                {{ session('error') }}
            </div>
        @endif

        <form method="post" action="{{ route('whatsapp.verify.submit') }}" class="auth-form">
            @csrf
            
            <div class="field">
                <label for="otp">6-Digit Code</label>
                <input 
                    type="text" 
                    id="otp" 
                    name="otp" 
                    placeholder="123456"
                    required
                    maxlength="6"
                    style="text-align: center; font-size: 24px; letter-spacing: 5px;"
                >
            </div>

            <button type="submit" class="btn btn--solid btn--full" style="background-color: #25D366; border-color: #25D366;">Verify & Login</button>
        </form>

        <div class="auth-footer">
            <p><a href="{{ route('site.login') }}">Back to Login</a></p>
        </div>
    </div>
</section>
@endsection
