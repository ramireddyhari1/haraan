@extends('site.layout')
@section('content')
<section class="auth-shell">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Sign in</h1>
            <p>Welcome back! Access your bookings and reservations.</p>
        </div>

        <form method="post" action="/auth/login" class="auth-form">
            @csrf
            
            <div class="field">
                <label for="email">Email address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="you@example.com"
                    required
                    autocomplete="email"
                >
            </div>

            <div class="field">
                <div class="field__label-row">
                    <label for="password">Password</label>
                    <a href="#" class="field__link">Forgot?</a>
                </div>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                >
            </div>

            <div class="field--checkbox">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Keep me signed in</label>
            </div>

            <button type="submit" class="btn btn--solid btn--full">Sign in to Account</button>
        </form>

        <div style="text-align: center; margin: 20px 0; color: #666;">
            <span>&mdash; OR &mdash;</span>
        </div>

        <form method="post" action="{{ route('whatsapp.request') }}" class="auth-form">
            @csrf
            
            <div class="field">
                <label for="phone">Phone Number (WhatsApp)</label>
                <input 
                    type="text" 
                    id="phone" 
                    name="phone" 
                    placeholder="e.g. 919876543210"
                    required
                >
                <small style="color: #666; margin-top: 5px; display: block;">We will send a 6-digit code to this number.</small>
            </div>

            <button type="submit" class="btn btn--solid btn--full" style="background-color: #25D366; border-color: #25D366;">Login with WhatsApp</button>
        </form>

        <div class="auth-footer">
            <p>Don't have an account? <a href="/register">Create one</a></p>
        </div>
    </div>
</section>
@endsection
