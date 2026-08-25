<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\AdminCouponService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Coupon::class);

        $scope = $request->string('scope')->toString();
        $status = $request->string('status')->toString();
        $q = $request->string('q')->toString();

        $coupons = Coupon::query()
            ->with(['vendor.store'])
            ->when(
                $q !== '',
                fn ($query) => $query->where('code', 'like', '%'.strtoupper($q).'%'),
            )
            ->when(
                in_array($scope, array_column(CouponScope::cases(), 'value'), true),
                fn ($query) => $query->where('scope', $scope),
            )
            ->when(
                $status === 'active',
                fn ($query) => $query->where('is_active', true),
            )
            ->when(
                $status === 'inactive',
                fn ($query) => $query->where('is_active', false),
            )
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.coupons.index', [
            'coupons' => $coupons,
            'q' => $q,
            'scope' => $scope,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Coupon::class);

        return view('admin.coupons.create', $this->formOptions());
    }

    public function store(StoreCouponRequest $request, AdminCouponService $coupons): RedirectResponse
    {
        $coupon = $coupons->create($request->validated());

        return redirect()
            ->route('admin.coupons.edit', $coupon)
            ->with('status', __('Coupon created.'));
    }

    public function show(Coupon $coupon): View
    {
        $this->authorize('view', $coupon);

        $coupon->load([
            'vendor.store',
            'currency',
            'products.translations',
            'categories.translations',
        ]);

        return view('admin.coupons.show', [
            'coupon' => $coupon,
        ]);
    }

    public function edit(Coupon $coupon): View
    {
        $this->authorize('update', $coupon);

        $coupon->load(['products:id', 'categories:id']);

        return view('admin.coupons.edit', array_merge($this->formOptions(), [
            'coupon' => $coupon,
        ]));
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon, AdminCouponService $coupons): RedirectResponse
    {
        $coupons->update($coupon, $request->validated());

        return redirect()
            ->route('admin.coupons.edit', $coupon)
            ->with('status', __('Coupon updated.'));
    }

    public function updateStatus(Request $request, Coupon $coupon, AdminCouponService $coupons): RedirectResponse
    {
        $this->authorize('update', $coupon);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $coupons->setActive($coupon, (bool) $validated['is_active']);

        return back()->with('status', $validated['is_active']
            ? __('Coupon activated.')
            : __('Coupon deactivated.'));
    }

    /**
     * @return array{
     *     vendors: Collection<int, Vendor>,
     *     currencies: Collection<int, Currency>,
     *     categories: Collection<int, Category>,
     *     products: Collection<int, Product>,
     *     scopes: list<CouponScope>,
     *     types: list<CouponType>
     * }
     */
    private function formOptions(): array
    {
        return [
            'vendors' => Vendor::query()
                ->with('store:id,vendor_id,name')
                ->orderBy('id')
                ->get(),
            'currencies' => Currency::query()->orderBy('code')->get(),
            'categories' => Category::query()
                ->with('translations')
                ->orderBy('position')
                ->orderBy('id')
                ->get(),
            'products' => Product::query()
                ->with('translations')
                ->orderByDesc('id')
                ->limit(200)
                ->get(),
            'scopes' => CouponScope::cases(),
            'types' => CouponType::cases(),
        ];
    }
}
