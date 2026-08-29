<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\DestroyCustomerAddressRequest;
use App\Http\Requests\Account\SetDefaultCustomerAddressRequest;
use App\Http\Requests\Account\StoreCustomerAddressRequest;
use App\Http\Requests\Account\UpdateCustomerAddressRequest;
use App\Models\CustomerAddress;
use App\Services\CustomerAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CustomerAddressController extends Controller
{
    public function __construct(
        private readonly CustomerAddressService $addresses,
    ) {}

    public function index(Request $request): View
    {
        if (! $request->user()?->can('viewAny', CustomerAddress::class)) {
            abort(404);
        }

        $locale = app()->getLocale();
        $rows = CustomerAddress::query()
            ->with(['governorate', 'city'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn (CustomerAddress $address): array => $this->presentRow($address, $locale))
            ->all();

        return view('account.addresses', [
            'addresses' => $rows,
        ]);
    }

    public function create(Request $request): View
    {
        if (! $request->user()?->can('create', CustomerAddress::class)) {
            abort(404);
        }

        return view('account.addresses.create', [
            'address' => null,
            'governorates' => $this->addresses->governoratesForLocale(app()->getLocale()),
        ]);
    }

    public function store(StoreCustomerAddressRequest $request): RedirectResponse
    {
        $this->addresses->create($request->user(), $request->validatedAddress());

        return redirect()
            ->route('account.addresses')
            ->with('status', __('Address saved.'));
    }

    public function edit(Request $request, CustomerAddress $customerAddress): View
    {
        if (! $request->user()?->can('update', $customerAddress)) {
            abort(404);
        }

        return view('account.addresses.edit', [
            'address' => $customerAddress,
            'governorates' => $this->addresses->governoratesForLocale(app()->getLocale()),
        ]);
    }

    public function update(UpdateCustomerAddressRequest $request, CustomerAddress $customerAddress): RedirectResponse
    {
        $this->addresses->update($customerAddress, $request->validatedAddress());

        return redirect()
            ->route('account.addresses')
            ->with('status', __('Address updated.'));
    }

    public function destroy(DestroyCustomerAddressRequest $request, CustomerAddress $customerAddress): RedirectResponse
    {
        $this->addresses->delete($customerAddress);

        return redirect()
            ->route('account.addresses')
            ->with('status', __('Address deleted.'));
    }

    public function setDefault(SetDefaultCustomerAddressRequest $request, CustomerAddress $customerAddress): RedirectResponse
    {
        $this->addresses->setDefault($customerAddress);

        return redirect()
            ->route('account.addresses')
            ->with('status', __('Default address updated.'));
    }

    /**
     * @return array{
     *     id: int,
     *     label: string,
     *     recipient_name: string,
     *     phone: string,
     *     summary: string,
     *     is_default: bool,
     * }
     */
    private function presentRow(CustomerAddress $address, string $locale): array
    {
        $gov = $locale === 'en'
            ? (string) ($address->governorate?->name_en ?? '')
            : (string) ($address->governorate?->name_ar ?? '');
        $city = $locale === 'en'
            ? (string) ($address->city?->name_en ?? '')
            : (string) ($address->city?->name_ar ?? '');

        return [
            'id' => (int) $address->id,
            'label' => (string) ($address->label ?: __('Address')),
            'recipient_name' => (string) $address->recipient_name,
            'phone' => (string) $address->phone,
            'summary' => trim($address->line1.($address->line2 ? ', '.$address->line2 : '').' — '.$city.', '.$gov),
            'is_default' => (bool) $address->is_default,
        ];
    }
}
