<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStoreRequest;
use App\Models\Currency;
use App\Services\StoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function edit(): View
    {
        $store = auth()->user()->vendor?->store;

        abort_unless($store, 404);
        $this->authorize('update', $store);

        return view('vendor.store', [
            'store' => $store->loadMissing('defaultCurrency'),
            'currencies' => Currency::query()->active()->orderBy('code')->get(),
        ]);
    }

    public function update(UpdateStoreRequest $request, StoreService $stores): RedirectResponse
    {
        $store = $request->user()->vendor?->store;

        abort_unless($store, 404);

        $stores->update(
            $store,
            $request->safe()->only([
                'name',
                'description',
                'contact_email',
                'contact_phone',
                'default_currency_code',
            ]),
            $request->file('logo'),
            $request->file('banner'),
        );

        return redirect()
            ->route('vendor.store')
            ->with('status', __('Store profile saved.'));
    }
}
