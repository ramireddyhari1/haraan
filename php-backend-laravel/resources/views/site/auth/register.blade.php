@extends('site.layout')
@section('content')
<section class="auth-shell">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Create account</h1>
            <p>Join Haraan to book events and reserve turfs in seconds.</p>
        </div>

        <form method="post" action="/auth/register" class="auth-form">
            @csrf

            <div class="field">
                <label for="name">Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="Your full name"
                    required
                    autocomplete="name"
                >
            </div>

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
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    required
                    autocomplete="new-password"
                >
            </div>

            <button type="submit" class="btn btn--solid btn--full">Create Account</button>
        </form>

        <div class="auth-footer">
            <p>Already have an account? <a href="/login">Sign in</a></p>
        </div>
    </div>
</section>
@endsection
