@extends('layouts.app')

@section('content')
<div class="dashboard-container">
    <h2>Admin Dashboard</h2>
    <p>Welcome, Administrator {{ $admin?->full_name }}</p>

    <nav style="margin-bottom: 1.5rem;">
        <ul style="list-style: none; padding: 0; display: flex; gap: 1rem;">
            <li><a href="{{ route('dashboard') }}">Doctor Dashboard</a></li>
            <li><a href="{{ route('admin.patients.index') }}">Patients</a></li>
            <li><a href="{{ route('admin.doctors.index') }}">Doctors</a></li>
        </ul>
    </nav>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Logout</button>
    </form>
</div>
@endsection
