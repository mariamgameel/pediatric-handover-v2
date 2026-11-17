@extends('layouts.app')

@section('content')
<div class="admin-patient-edit-container">
    <h2>Edit Patient (Admin)</h2>

    <div>
        <strong>Patient ID:</strong> <span>{{ $patient->patient_id }}</span> <em>(Immutable)</em>
    </div>

    <form method="POST" action="{{ route('admin.patients.update', $patient->patient_id) }}">
        @csrf
        @method('PUT')

        <div>
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" value="{{ old('full_name', $patient->full_name) }}" required>
        </div>

        <div>
            <label for="date_of_birth">Date of Birth</label>
            <input type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $patient->date_of_birth->format('Y-m-d')) }}" required>
        </div>

        <div>
            <label for="gender">Gender</label>
            <select id="gender" name="gender" required>
                @foreach ($genders as $g)
                    <option value="{{ $g }}" @selected(old('gender', $patient->gender) === $g)>{{ ucfirst($g) }}</option>
                @endforeach
            </select>
        </div>

        <div style="margin-top: 1rem;">
            <button type="submit">Save Changes</button>
            <a href="{{ route('admin.patients.show', $patient->patient_id) }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
