@extends('layouts.app')

@section('content')
<div class="auth-container">
    <h2>Admin Login</h2>

    <form method="POST" action="{{ route('admin.login') }}">
        @csrf

        <div>
            <label for="email">Admin Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>

        <div>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div>
            <button type="submit">Sign In as Admin</button>
        </div>
    </form>
</div>
@endsection
