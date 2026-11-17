<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Admin;
use App\Models\Doctor;
use App\Models\Patient;
use App\Policies\AdminPolicy;
use App\Policies\DoctorPolicy;
use App\Policies\PatientPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Patient::class, PatientPolicy::class);
        Gate::policy(Doctor::class, DoctorPolicy::class);
        Gate::policy(Admin::class, AdminPolicy::class);

        Password::defaults(function () {
            return Password::min(8);
        });

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');
            return Limit::perMinute(5)->by($request->ip() . '|' . strtolower($email));
        });

        RateLimiter::for('register', function (Request $request) {
            $email = (string) $request->input('email');
            return Limit::perMinute(5)->by($request->ip() . '|' . strtolower($email));
        });

        RateLimiter::for('google-auth', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->ip());
        });
    }
}
