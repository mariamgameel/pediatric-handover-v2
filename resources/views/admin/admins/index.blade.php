@extends('layouts.app')

@section('content')
<div class="admin-accounts-container">
    <h2>System Administrator Accounts</h2>

    <table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th>Admin ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Last Login</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($admins as $admin)
                <tr>
                    <td>{{ $admin->admin_id }}</td>
                    <td>{{ $admin->full_name }}</td>
                    <td>{{ $admin->email }}</td>
                    <td>{{ $admin->last_login_at?->format('Y-m-d H:i:s') ?? 'Never' }}</td>
                    <td>{{ $admin->trashed() ? 'deleted' : 'active' }}</td>
                    <td>
                        @if ($admin->admin_id === $currentAdmin->admin_id)
                            <em>Current Account</em>
                        @elseif ($admin->trashed())
                            <em>Deleted</em>
                        @else
                            <form method="POST" action="{{ route('admin.admins.destroy', $admin->admin_id) }}" style="display: inline-block;">
                                @csrf
                                @method('DELETE')
                                <label style="font-size: 0.85em; margin-right: 0.5rem;">
                                    <input type="checkbox" name="confirm_delete" value="1" required>
                                    Confirm
                                </label>
                                <button type="submit" onclick="return confirm('Are you sure you want to delete this administrator?');">Delete Admin</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top: 1.5rem;">
        <a href="{{ route('admin.dashboard') }}">Back to Admin Dashboard</a>
    </p>
</div>
@endsection
