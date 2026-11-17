@extends('layouts.app')

@section('content')
<div class="dashboard-container">
    <h2>Admin Dashboard</h2>
    <p>Welcome, Administrator {{ $admin?->full_name }}</p>

    <form method="POST" action="{{ route('admin.logout') }}">
        @csrf
        <button type="submit">Logout</button>
    </form>
</div>
@endsection
