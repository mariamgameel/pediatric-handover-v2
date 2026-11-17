<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\CannotDeleteAdminException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteAdminRequest;
use App\Models\Admin;
use App\Services\AdminAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminController extends Controller
{
    /**
     * Display a listing of system administrators.
     */
    public function index(Request $request, AdminAccountService $service): View
    {
        $admin = auth('admin')->user();
        Gate::forUser($admin)->authorize('viewAny', Admin::class);

        $admins = $service->listAdmins();

        return view('admin.admins.index', [
            'admins' => $admins,
            'currentAdmin' => $admin,
        ]);
    }

    /**
     * Soft delete an administrator account.
     */
    public function destroy(DeleteAdminRequest $request, string $admin_id, AdminAccountService $service): RedirectResponse
    {
        $admin = auth('admin')->user();
        $targetAdmin = Admin::findOrFail((int) $admin_id);

        Gate::forUser($admin)->authorize('delete', $targetAdmin);

        try {
            $service->deleteAdmin($admin, $targetAdmin);

            return redirect()->route('admin.admins.index')
                ->with('success', "Administrator {$targetAdmin->full_name} was deleted successfully.");
        } catch (CannotDeleteAdminException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
