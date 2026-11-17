<?php

declare(strict_types=1);

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\PatientService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PatientController extends Controller
{
    /**
     * Display a listing of patients for doctors.
     */
    public function index(Request $request, PatientService $patientService): View
    {
        $doctor = auth('doctor')->user();
        Gate::forUser($doctor)->authorize('viewAny', Patient::class);

        $patients = $patientService->paginate($request->only([
            'search',
            'gender',
            'direction',
        ]));

        return view('patients.index', [
            'patients' => $patients,
            'search' => $request->input('search'),
            'gender' => $request->input('gender'),
            'direction' => $request->input('direction', 'asc'),
        ]);
    }

    /**
     * Display patient details for doctors.
     */
    public function show(string $patient_id): View
    {
        $patient = Patient::findOrFail($patient_id);

        $doctor = auth('doctor')->user();
        Gate::forUser($doctor)->authorize('view', $patient);

        return view('patients.show', [
            'patient' => $patient,
        ]);
    }
}
