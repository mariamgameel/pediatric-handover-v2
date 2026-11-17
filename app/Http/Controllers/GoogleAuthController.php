<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GoogleAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleAuthController extends Controller
{
    public function redirect(GoogleAuthService $googleAuthService): RedirectResponse
    {
        return $googleAuthService->getRedirect();
    }

    public function callback(Request $request, GoogleAuthService $googleAuthService): RedirectResponse
    {
        return $googleAuthService->handleCallback($request);
    }
}
