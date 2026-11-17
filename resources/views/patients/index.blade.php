@extends('layouts.app')

@section('content')
<div class="patients-container">
    <h2>Patients Directory</h2>

    <form method="GET" action="{{ route('patients.index') }}" style="margin-bottom: 1.5rem;">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search by ID or name...">

        <select name="gender">
            <option value="">All Genders</option>
            <option value="male" @selected($gender === 'male')>Male</option>
            <option value="female" @selected($gender === 'female')>Female</option>
        </select>

        <select name="direction">
            <option value="asc" @selected($direction === 'asc')>Sort Name (A-Z)</option>
            <option value="desc" @selected($direction === 'desc')>Sort Name (Z-A)</option>
        </select>

        <button type="submit">Filter</button>
        <a href="{{ route('patients.index') }}">Reset</a>
    </form>

    <table border="1" cellpadding="6" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th>Patient ID</th>
                <th>Full Name</th>
                <th>Date of Birth</th>
                <th>Gender</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($patients as $patient)
                <tr>
                    <td>{{ $patient->patient_id }}</td>
                    <td>{{ $patient->full_name }}</td>
                    <td>{{ $patient->date_of_birth->format('Y-m-d') }}</td>
                    <td>{{ ucfirst($patient->gender) }}</td>
                    <td>
                        <a href="{{ route('patients.show', $patient->patient_id) }}">View Details</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No patients found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top: 1rem;">
        {{ $patients->links() }}
    </div>

    <p style="margin-top: 1.5rem;">
        <a href="{{ route('dashboard') }}">Back to Dashboard</a>
    </p>
</div>
@endsection
