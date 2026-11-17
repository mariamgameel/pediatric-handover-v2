@extends('layouts.app')

@section('content')
<div class="auth-container">
    <h2>Doctor Login</h2>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>

        <div>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div>
            <button type="submit">Sign In</button>
        </div>
    </form>

    <div style="margin-top: 1rem;">
        <a href="{{ route('auth.google.redirect') }}">Sign in with Google</a>
    </div>

    <p style="margin-top: 1rem;">Don't have an account? <a href="{{ route('register') }}">Register here</a>.</p>
</div>
@endsection
