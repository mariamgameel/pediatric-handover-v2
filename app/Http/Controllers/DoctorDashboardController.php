<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class DoctorDashboardController extends Controller
{
    public function index(): View
    {
        return view('doctor.dashboard', [
            'doctor' => auth('doctor')->user(),
        ]);
    }
}
