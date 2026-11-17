@extends('layouts.app')

@section('content')
<div class="profile-container">
    <h2>Doctor Profile</h2>

    <div>
        <strong>Doctor ID:</strong> <span>{{ $doctor_id }}</span>
    </div>

    <div>
        <strong>Full Name:</strong> <span>{{ $full_name }}</span>
    </div>

    <div>
        <strong>Email:</strong> <span>{{ $email }}</span>
    </div>

    <div>
        <strong>Phone Number:</strong> <span>{{ $phone_number ?? 'Not provided' }}</span>
    </div>

    <div>
        <strong>Last Login:</strong> <span>{{ $last_login_at ? $last_login_at->toIso8601String() : 'Never' }}</span>
    </div>

    <div>
        <strong>Google Account Linked:</strong> <span>{{ $is_google_linked ? 'Yes' : 'No' }}</span>
    </div>

    <div style="margin-top: 1rem;">
        <a href="{{ route('profile.edit') }}">Edit Profile</a> |
        <a href="{{ route('dashboard') }}">Back to Dashboard</a>
    </div>
</div>
@endsection
