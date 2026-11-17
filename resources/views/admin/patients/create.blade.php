@extends('layouts.app')

@section('content')
<div class="admin-patient-create-container">
    <h2>Create Patient (Admin)</h2>

    <form method="POST" action="{{ route('admin.patients.store') }}">
        @csrf

        <div>
            <label for="patient_id">Hospital Patient ID</label>
            <input type="text" id="patient_id" name="patient_id" value="{{ old('patient_id') }}" required autofocus>
            <small>Must match hospital pattern and be unique.</small>
        </div>

        <div>
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" value="{{ old('full_name') }}" required>
        </div>

        <div>
            <label for="date_of_birth">Date of Birth</label>
            <input type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}" required>
        </div>

        <div>
            <label for="gender">Gender</label>
            <select id="gender" name="gender" required>
                <option value="">Select Gender</option>
                @foreach ($genders as $g)
                    <option value="{{ $g }}" @selected(old('gender') === $g)>{{ ucfirst($g) }}</option>
                @endforeach
            </select>
        </div>

        <div style="margin-top: 1rem;">
            <button type="submit">Create Patient</button>
            <a href="{{ route('admin.patients.index') }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
