<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\CannotDeletePatientWithUpdatesException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeletePatientRequest;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Models\Patient;
use App\Services\PatientService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PatientController extends Controller
{
    /**
     * Display a listing of patients for administrators.
     */
    public function index(Request $request, PatientService $patientService): View
    {
        $admin = auth('admin')->user();
        Gate::forUser($admin)->authorize('viewAny', Patient::class);

        $patients = $patientService->paginate($request->only([
            'search',
            'gender',
            'direction',
        ]));

        return view('admin.patients.index', [
            'patients' => $patients,
            'search' => $request->input('search'),
            'gender' => $request->input('gender'),
            'direction' => $request->input('direction', 'asc'),
        ]);
    }

    /**
     * Show the form for creating a new patient.
     */
    public function create(): View
    {
        $admin = auth('admin')->user();
        Gate::forUser($admin)->authorize('create', Patient::class);

        return view('admin.patients.create', [
            'genders' => config('hospital.genders', ['male', 'female']),
        ]);
    }

    /**
     * Store a newly created patient in storage.
     */
    public function store(StorePatientRequest $request, PatientService $patientService): RedirectResponse
    {
        $admin = auth('admin')->user();
        Gate::forUser($admin)->authorize('create', Patient::class);

        $patient = $patientService->create($request->validated());

        return redirect()->route('admin.patients.show', $patient->patient_id)
            ->with('success', "Patient {$patient->patient_id} created successfully.");
    }

    /**
     * Display the specified patient for administrators.
     */
    public function show(string $patient_id): View
    {
        $patient = Patient::findOrFail($patient_id);

        $admin = auth('admin')->user();
        Gate::forUser($admin)->authorize('view', $patient);

        return view('admin.patients.show', [
            'patient' => $patient,
        ]);
    }

    /**
     * Show the form for editing the specified patient.
     */
    public function edit(string $patient_id): View
    {
        $patient = Patient::findOrFail($patient_id);

        $admin = auth('admin')->user();
        Gate::forUser($admin)->authorize('update', $patient);

        return view('admin.patients.edit', [
            'patient' => $patient,
            'genders' => config('hospital.genders', ['male', 'female']),
        ]);
    }

    /**
     * Update the specified patient in storage.
     */
    public function update(UpdatePatientRequest $request, string $patient_id, PatientService $patientService): RedirectResponse
    {
        $patient = Patient::findOrFail($patient_id);

        $admin = auth('admin')->user();
        Gate::forUser($admin)->authorize('update', $patient);

        $patientService->update($patient, $request->only([
            'full_name',
            'date_of_birth',
            'gender',
        ]));

        return redirect()->route('admin.patients.show', $patient->patient_id)
            ->with('success', "Patient {$patient->patient_id} updated successfully.");
    }

    /**
     * Remove the specified patient from storage.
     */
    public function destroy(DeletePatientRequest $request, string $patient_id, PatientService $patientService): RedirectResponse
    {
        $patient = Patient::findOrFail($patient_id);

        $admin = auth('admin')->user();
        Gate::forUser($admin)->authorize('delete', $patient);

        try {
            $patientService->delete($patient);

            return redirect()->route('admin.patients.index')
                ->with('success', "Patient {$patient_id} was deleted successfully.");
        } catch (CannotDeletePatientWithUpdatesException) {
            return back()->with('error', 'Cannot delete patient: clinical history is retained permanently.');
        }
    }
}
