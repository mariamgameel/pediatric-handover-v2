@extends('layouts.app')

@section('content')
<div class="admin-doctor-details-container">
    <h2>Doctor Details</h2>

    <div class="doctor-info" style="margin-bottom: 2rem;">
        <div><strong>Doctor ID:</strong> <span>{{ $doctor->doctor_id }}</span></div>
        <div><strong>Full Name:</strong> <span>{{ $doctor->full_name }}</span></div>
        <div><strong>Display Name:</strong> <span>{{ $doctor->display_name }}</span></div>
        <div><strong>Email:</strong> <span>{{ $doctor->email }}</span></div>
        <div><strong>Phone Number:</strong> <span>{{ $doctor->phone_number ?? 'None' }}</span></div>
        <div><strong>Last Login:</strong> <span>{{ $doctor->last_login_at?->format('Y-m-d H:i:s') ?? 'Never' }}</span></div>
        <div><strong>Created At:</strong> <span>{{ $doctor->created_at?->format('Y-m-d H:i:s') }}</span></div>
        <div><strong>Deleted At:</strong> <span>{{ $doctor->deleted_at?->format('Y-m-d H:i:s') ?? 'Active' }}</span></div>
        <div><strong>Google Account Linked:</strong> <span>{{ $isGoogleLinked ? 'Yes' : 'No' }}</span></div>
        <div><strong>Status:</strong> <span>{{ $doctor->trashed() ? 'Deleted / Inactive' : 'Active' }}</span></div>
    </div>

    <div class="account-actions" style="margin-bottom: 2rem;">
        @if ($doctor->trashed())
            <form method="POST" action="{{ route('admin.doctors.restore', $doctor->doctor_id) }}">
                @csrf
                <button type="submit">Restore Doctor Account</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.doctors.destroy', $doctor->doctor_id) }}">
                @csrf
                @method('DELETE')
                <div style="margin-bottom: 0.5rem;">
                    <label>
                        <input type="checkbox" name="confirm_delete" value="1" required>
                        I confirm that I want to deactivate (soft-delete) this doctor account.
                    </label>
                </div>
                <button type="submit" onclick="return confirm('Are you sure you want to deactivate this doctor?');">Deactivate Doctor</button>
            </form>
        @endif
    </div>

    <hr style="margin: 2rem 0;">

    <div class="doctor-clinical-updates">
        <h3>Authored Clinical Updates (Read-Only)</h3>

        <table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Created At</th>
                    <th>Content Excerpt</th>
                    <th>Author</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clinicalUpdates as $update)
                    <tr>
                        <td>{{ $update->patient?->full_name ?? $update->patient_id }} ({{ $update->patient_id }})</td>
                        <td>{{ $update->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($update->content, 120) }}</td>
                        <td>{{ $doctor->display_name }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">No clinical updates authored by this doctor.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top: 1rem;">
            {{ $clinicalUpdates->links() }}
        </div>
    </div>

    <p style="margin-top: 1.5rem;">
        <a href="{{ route('admin.doctors.index') }}">Back to Doctor Directory</a>
    </p>
</div>
@endsection
