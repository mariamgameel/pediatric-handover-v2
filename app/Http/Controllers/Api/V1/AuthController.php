<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\GoogleIdTokenVerifier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ApiGoogleAuthRequest;
use App\Http\Requests\Api\Auth\ApiLoginRequest;
use App\Http\Requests\Api\Auth\ApiRegisterRequest;
use App\Http\Resources\DoctorResource;
use App\Models\Doctor;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AuthController extends Controller
{
    /**
     * Register a new doctor with email and password.
     */
    public function register(ApiRegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $doctor = Doctor::create([
            'doctor_id' => $validated['doctor_id'],
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'phone_number' => $validated['phone_number'] ?? null,
            'last_login_at' => now(),
        ]);

        $deviceName = (string) ($request->input('device_name') ?: 'default');
        $token = $doctor->createToken($deviceName, ['doctor'])->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'doctor' => new DoctorResource($doctor),
        ], 'Registered successfully.', 201);
    }

    /**
     * Authenticate an existing doctor with email and password.
     */
    public function login(ApiLoginRequest $request): JsonResponse
    {
        $email = (string) $request->input('email');
        $password = (string) $request->input('password');

        $doctor = Doctor::withTrashed()->where('email', $email)->first();

        if ($doctor === null || $doctor->password === null || !Hash::check($password, $doctor->password)) {
            return ApiResponse::error('Invalid email or password.', 'invalid_credentials', 401);
        }

        if ($doctor->trashed()) {
            return ApiResponse::error('Your doctor account is inactive.', 'unauthenticated', 401);
        }

        $doctor->update(['last_login_at' => now()]);

        $deviceName = (string) ($request->input('device_name') ?: 'default');
        $abilities = $doctor->isAdmin() ? ['doctor', 'admin'] : ['doctor'];
        $token = $doctor->createToken($deviceName, $abilities)->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'doctor' => new DoctorResource($doctor),
        ], 'Logged in successfully.');
    }

    /**
     * Authenticate or register a doctor using a Google ID token.
     */
    public function google(ApiGoogleAuthRequest $request, GoogleIdTokenVerifier $verifier): JsonResponse
    {
        try {
            $payload = $verifier->verify((string) $request->input('id_token'));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 'invalid_token', 401);
        }

        if (!$payload['email_verified']) {
            return ApiResponse::error('Google email address is not verified.', 'unverified_email', 422);
        }

        $googleId = $payload['sub'];
        $email = $payload['email'];

        // 1. Try finding doctor by google_id
        $doctor = Doctor::withTrashed()->where('google_id', $googleId)->first();

        // 2. If not found by google_id, try finding by verified email
        if ($doctor === null) {
            $doctor = Doctor::withTrashed()->where('email', $email)->first();

            if ($doctor !== null) {
                // Reject if already linked to a different Google account
                if ($doctor->google_id !== null && $doctor->google_id !== $googleId) {
                    return ApiResponse::error('This account is already linked to a different Google account.', 'conflict', 409);
                }

                $doctor->update(['google_id' => $googleId]);
            }
        }

        // If existing doctor found
        if ($doctor !== null) {
            if ($doctor->trashed()) {
                return ApiResponse::error('Your doctor account is inactive.', 'unauthenticated', 401);
            }

            $doctor->update(['last_login_at' => now()]);

            $deviceName = (string) ($request->input('device_name') ?: 'default');
            $abilities = $doctor->isAdmin() ? ['doctor', 'admin'] : ['doctor'];
            $token = $doctor->createToken($deviceName, $abilities)->plainTextToken;

            return ApiResponse::success([
                'token' => $token,
                'token_type' => 'Bearer',
                'doctor' => new DoctorResource($doctor),
            ], 'Logged in successfully with Google.');
        }

        // 3. New doctor registration flow
        $doctorId = $request->input('doctor_id');

        if (empty($doctorId)) {
            return ApiResponse::error(
                'Hospital Doctor ID is required for registration.',
                'doctor_id_required',
                422,
                null,
                [
                    'email' => $email,
                    'name' => $payload['name'],
                ]
            );
        }

        $doctorIdLength = (int) config('hospital.doctor_id_max_length', 64);
        $validator = Validator::make($request->only('doctor_id'), [
            'doctor_id' => [
                'required',
                'string',
                'max:' . $doctorIdLength,
                Rule::unique(Doctor::class, 'doctor_id'),
            ],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 'validation_failed', 422, $validator->errors()->toArray());
        }

        $doctor = Doctor::create([
            'doctor_id' => (string) $doctorId,
            'full_name' => $payload['name'] !== '' ? $payload['name'] : 'Dr. ' . $doctorId,
            'email' => $email,
            'google_id' => $googleId,
            'password' => null,
            'last_login_at' => now(),
        ]);

        $deviceName = (string) ($request->input('device_name') ?: 'default');
        $token = $doctor->createToken($deviceName, ['doctor'])->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'doctor' => new DoctorResource($doctor),
        ], 'Registered and logged in successfully with Google.', 201);
    }

    /**
     * Get the authenticated doctor's profile.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var Doctor $doctor */
        $doctor = $request->user();

        return ApiResponse::success([
            'doctor' => new DoctorResource($doctor),
        ]);
    }

    /**
     * Log out the authenticated doctor by revoking the current personal access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();
        auth()->forgetGuards();

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    /**
     * Log out from all devices by revoking all personal access tokens.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()?->tokens()->delete();
        auth()->forgetGuards();

        return ApiResponse::success(null, 'Logged out from all devices.');
    }
}
