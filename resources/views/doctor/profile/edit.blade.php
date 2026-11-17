@extends('layouts.app')

@section('content')
<div class="profile-edit-container">
    <h2>Edit Profile</h2>

    <div>
        <strong>Doctor ID:</strong> <span>{{ $doctor_id }}</span> <em>(Immutable)</em>
    </div>

    <form method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PATCH')

        <div>
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" value="{{ old('full_name', $full_name) }}" required>
        </div>

        <div>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required>
        </div>

        <div>
            <label for="phone_number">Phone Number</label>
            <input type="text" id="phone_number" name="phone_number" value="{{ old('phone_number', $phone_number) }}">
        </div>

        <div>
            <button type="submit">Save Changes</button>
            <a href="{{ route('profile.show') }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
