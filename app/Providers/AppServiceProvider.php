<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\GoogleIdTokenVerifier;
use App\Models\Doctor;
use App\Models\Patient;
use App\Policies\DoctorPolicy;
use App\Policies\PatientPolicy;
use App\Services\Google\JwtGoogleIdTokenVerifier;
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
        $this->app->bind(GoogleIdTokenVerifier::class, JwtGoogleIdTokenVerifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Patient::class, PatientPolicy::class);
        Gate::policy(Doctor::class, DoctorPolicy::class);

        Gate::define('viewApiDocs', function (?Doctor $user = null): bool {
            return (bool) env('API_DOCS_ENABLED', !app()->isProduction());
        });

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

        RateLimiter::for('api-login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));
            return Limit::perMinute(5)->by($request->ip() . '|' . $email);
        });

        RateLimiter::for('api-register', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->ip());
        });

        RateLimiter::for('api-google', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->ip());
        });
    }
}
