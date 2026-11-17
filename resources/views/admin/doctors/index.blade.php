@extends('layouts.app')

@section('content')
<div class="admin-doctors-container">
    <h2>Doctor Directory & Management</h2>

    <form method="GET" action="{{ route('admin.doctors.index') }}" style="margin-bottom: 1.5rem;">
        <label for="search">Search:</label>
        <input type="text" id="search" name="search" value="{{ $search }}" placeholder="Search by ID, name, email">

        <label for="status">Status:</label>
        <select id="status" name="status">
            <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
            <option value="deleted" {{ $status === 'deleted' ? 'selected' : '' }}>Deleted</option>
            <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All</option>
        </select>

        <button type="submit">Filter</button>
        <a href="{{ route('admin.doctors.index') }}">Reset</a>
    </form>

    <table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th>Doctor ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($doctors as $doctor)
                <tr>
                    <td>{{ $doctor->doctor_id }}</td>
                    <td>{{ $doctor->display_name }}</td>
                    <td>{{ $doctor->email }}</td>
                    <td>{{ $doctor->trashed() ? 'Deleted' : 'Active' }}</td>
                    <td>{{ $doctor->last_login_at?->format('Y-m-d H:i:s') ?? 'Never' }}</td>
                    <td>
                        <a href="{{ route('admin.doctors.show', $doctor->doctor_id) }}">View Details</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No doctors found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top: 1rem;">
        {{ $doctors->links() }}
    </div>

    <p style="margin-top: 1.5rem;">
        <a href="{{ route('admin.dashboard') }}">Back to Admin Dashboard</a>
    </p>
</div>
@endsection
