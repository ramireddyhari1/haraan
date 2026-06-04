@extends('site.layout')
@section('content')
<div class="card">
    <h2>Register</h2>
    <form method="post" action="/auth/register">
        @csrf
        <label>Name<br><input type="text" name="name" required></label><br><br>
        <label>Email<br><input type="email" name="email" required></label><br><br>
        <label>Password<br><input type="password" name="password" required></label><br><br>
        <button class="btn">Create Account</button>
    </form>
</div>
@endsection
