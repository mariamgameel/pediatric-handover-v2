@extends('layouts.app')

@section('content')
<div class="patient-details-container">
    <h2>Patient Details</h2>

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

    <hr style="margin: 2rem 0;">

    <!-- PLACEHOLDER: CLINICAL UPDATES SECTION (To be implemented in Phase 5) -->
    <div id="clinical-updates-section">
        <h3>Clinical Updates</h3>
        <p><em>No updates displayed yet. Clinical updates handover stream will be implemented in Phase 5.</em></p>
    </div>
    <!-- END PLACEHOLDER -->

    <p style="margin-top: 1.5rem;">
        <a href="{{ route('patients.index') }}">Back to Patient Directory</a>
    </p>
</div>
@endsection
