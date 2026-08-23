<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\VendorApplicationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVendorApplicationRequest;
use App\Models\VendorApplication;
use App\Services\VendorApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VendorApplicationController extends Controller
{
    public function show(): View
    {
        $user = auth()->user();

        return view('account.vendor-application', [
            'application' => $user->latestVendorApplication,
            'canApply' => $user->can('create', VendorApplication::class)
                && $user->vendorApplications()->pending()->doesntExist(),
        ]);
    }

    public function store(StoreVendorApplicationRequest $request, VendorApplicationService $applications): RedirectResponse
    {
        try {
            $applications->submit(
                $request->user(),
                $request->validated('store_name'),
                $request->validated('note'),
            );
        } catch (VendorApplicationException $exception) {
            return back()->withErrors(['store_name' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('account.vendor-application')
            ->with('status', __('Your vendor application has been submitted.'));
    }
}
