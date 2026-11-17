@extends('layouts.app')

@section('content')
<div class="admin-patient-show-container">
    <h2>Patient Details (Admin)</h2>

    <div>
        <strong>Patient ID:</strong> <span>{{ $patient->patient_id }}</span>
    </div>

    <div>
        <strong>Full Name:</strong> <span>{{ $patient->full_name }}</span>
    </div>

    <div>
        <strong>Date of Birth:</strong> <span>{{ $patient->date_of_birth->format('Y-m-d') }}</span>
    </div>

    <div>
        <strong>Gender:</strong> <span>{{ ucfirst($patient->gender) }}</span>
    </div>

    <div>
        <strong>Created At:</strong> <span>{{ $patient->created_at->toIso8601String() }}</span>
    </div>

    <div style="margin-top: 1.5rem;">
        <a href="{{ route('admin.patients.edit', $patient->patient_id) }}">Edit Patient</a> |
        <a href="{{ route('admin.patients.index') }}">Back to Patient List</a>
    </div>

    <hr style="margin: 2rem 0;">

    <div class="danger-zone" style="border: 1px solid red; padding: 1rem;">
        <h3>Delete Patient</h3>
        <p>Warning: Deletion is permitted ONLY if the patient has no clinical history.</p>

        <form method="POST" action="{{ route('admin.patients.destroy', $patient->patient_id) }}">
            @csrf
            @method('DELETE')

            <div>
                <label>
                    <input type="checkbox" name="confirm_delete" value="1">
                    I confirm that I want to delete this patient permanently.
                </label>
            </div>

            <div style="margin-top: 0.5rem;">
                <button type="submit" onclick="return confirm('Are you sure you want to delete this patient?');">
                    Delete Patient
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
