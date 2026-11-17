@extends('layouts.app')

@section('content')
<div class="auth-container">
    <h2>Doctor Registration</h2>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div>
            <label for="doctor_id">Hospital Doctor ID</label>
            <input type="text" id="doctor_id" name="doctor_id" value="{{ old('doctor_id') }}" required autofocus>
        </div>

        <div>
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" value="{{ old('full_name') }}" required>
        </div>

        <div>
            <label for="email">Hospital Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required>
        </div>

        <div>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div>
            <label for="password_confirmation">Confirm Password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required>
        </div>

        <div>
            <label for="phone_number">Phone Number (Optional)</label>
            <input type="text" id="phone_number" name="phone_number" value="{{ old('phone_number') }}">
        </div>

        <div>
            <button type="submit">Register</button>
        </div>
    </form>

    <p>Already registered? <a href="{{ route('login') }}">Login here</a>.</p>
</div>
@endsection
