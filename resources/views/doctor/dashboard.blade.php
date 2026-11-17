@extends('layouts.app')

@section('content')
<div class="dashboard-container">
    <h2>Doctor Dashboard</h2>
    <p>Welcome, Dr. {{ $doctor?->full_name }} (ID: {{ $doctor?->doctor_id }})</p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Logout</button>
    </form>
</div>
@endsection
