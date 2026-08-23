<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VendorApplicationStatus;
use App\Exceptions\VendorApplicationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectVendorApplicationRequest;
use App\Models\VendorApplication;
use App\Services\VendorApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', VendorApplication::class);

        $status = $request->string('status')->toString();
        $applications = VendorApplication::query()
            ->with('user')
            ->when(
                in_array($status, array_column(VendorApplicationStatus::cases(), 'value'), true),
                fn ($query) => $query->where('status', $status),
                fn ($query) => $query->pending(),
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.vendor-applications.index', [
            'applications' => $applications,
            'status' => $status !== '' ? $status : VendorApplicationStatus::Pending->value,
        ]);
    }

    public function show(VendorApplication $vendorApplication): View
    {
        $this->authorize('view', $vendorApplication);

        return view('admin.vendor-applications.show', [
            'application' => $vendorApplication->load(['user', 'reviewer']),
        ]);
    }

    public function approve(VendorApplication $vendorApplication, VendorApplicationService $applications): RedirectResponse
    {
        $this->authorize('approve', $vendorApplication);

        try {
            $applications->approve($vendorApplication, auth()->user());
        } catch (VendorApplicationException $exception) {
            return back()->withErrors(['application' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.vendor-applications.show', $vendorApplication)
            ->with('status', __('The application was approved and the store was created.'));
    }

    public function reject(
        RejectVendorApplicationRequest $request,
        VendorApplication $vendorApplication,
        VendorApplicationService $applications,
    ): RedirectResponse {
        try {
            $applications->reject(
                $vendorApplication,
                $request->user(),
                $request->validated('rejection_reason'),
            );
        } catch (VendorApplicationException $exception) {
            return back()->withErrors(['application' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.vendor-applications.show', $vendorApplication)
            ->with('status', __('The application was rejected.'));
    }
}
