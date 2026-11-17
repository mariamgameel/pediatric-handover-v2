<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteDoctorRequest;
use App\Models\Doctor;
use App\Services\AdminAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DoctorController extends Controller
{
    /**
     * Display a listing of doctors for administrators.
     */
    public function index(Request $request, AdminAccountService $service): View
    {
        $admin = auth('admin')->user();
        Gate::forUser($admin)->authorize('viewAny', Doctor::class);

        $doctors = $service->paginateDoctors($request->only([
            'search',
            'status',
        ]));

        return view('admin.doctors.index', [
            'doctors' => $doctors,
            'search' => $request->input('search'),
            'status' => $request->input('status', 'active'),
        ]);
    }

    /**
     * Display the specified doctor and their authored clinical updates.
     */
    public function show(string $doctor_id, AdminAccountService $service): View
    {
        $doctor = $service->getDoctor($doctor_id);

        $admin = auth('admin')->user();
        Gate::forUser($admin)->authorize('view', $doctor);

        $clinicalUpdates = $service->getDoctorClinicalUpdates($doctor);

        return view('admin.doctors.show', [
            'doctor' => $doctor,
            'clinicalUpdates' => $clinicalUpdates,
            'isGoogleLinked' => $doctor->google_id !== null,
        ]);
    }

    /**
     * Soft delete the specified doctor.
     */
    public function destroy(DeleteDoctorRequest $request, string $doctor_id, AdminAccountService $service): RedirectResponse
    {
        $doctor = $service->getDoctor($doctor_id);

        $admin = auth('admin')->user();
        Gate::forUser($admin)->authorize('delete', $doctor);

        $service->deleteDoctor($doctor);

        return redirect()->route('admin.doctors.index')
            ->with('success', "Doctor {$doctor->doctor_id} has been deactivated.");
    }

    /**
     * Restore the specified soft-deleted doctor.
     */
    public function restore(Request $request, string $doctor_id, AdminAccountService $service): RedirectResponse
    {
        $doctor = $service->getDoctor($doctor_id);

        $admin = auth('admin')->user();
        Gate::forUser($admin)->authorize('restore', $doctor);

        $service->restoreDoctor($doctor);

        return redirect()->route('admin.doctors.show', $doctor->doctor_id)
            ->with('success', "Doctor {$doctor->doctor_id} has been restored.");
    }
}
