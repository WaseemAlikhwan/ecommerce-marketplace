<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\VendorOrderStatus;
use App\Exceptions\VendorOrderLifecycleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\AdvanceVendorOrderRequest;
use App\Models\VendorOrder;
use App\Services\OrderViewService;
use App\Services\VendorOrderLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class VendorOrderController extends Controller
{
    public function __construct(
        private readonly OrderViewService $orderViews,
        private readonly VendorOrderLifecycleService $lifecycle,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', VendorOrder::class);

        $user = $request->user();
        $user->loadMissing(['roles', 'vendor']);
        $vendor = $user->vendor;
        abort_unless($vendor !== null && $user->canAccessVendorPanel(), 404);

        $orders = VendorOrder::query()
            ->where('vendor_id', $vendor->id)
            ->with(['payment', 'parentOrder', 'currency'])
            ->latest('id')
            ->paginate(20);

        $rows = $this->orderViews->vendorIndexRows(
            $orders->getCollection(),
            app()->getLocale(),
        );

        return view('vendor.orders.index', [
            'orders' => $orders,
            'rows' => $rows,
        ]);
    }

    public function show(Request $request, VendorOrder $vendorOrder): View
    {
        $user = $request->user();
        $user->loadMissing(['roles', 'vendor']);
        abort_unless($user->canAccessVendorPanel(), 404);

        // Own-vendor only: fail closed as 404 (do not leak existence to other vendors).
        if ($user->vendor?->id !== $vendorOrder->vendor_id) {
            abort(404);
        }

        $this->authorize('view', $vendorOrder);

        $view = $this->orderViews->vendor($vendorOrder, app()->getLocale());
        $next = $this->lifecycle->nextStatus($vendorOrder->status);
        $canAdvance = $user->can('advance', $vendorOrder) && $next !== null;

        return view('vendor.orders.show', [
            'order' => $view,
            'canAdvance' => $canAdvance,
            'nextStatus' => $next?->value,
            'nextActionLabel' => $this->actionLabel($next),
        ]);
    }

    public function advance(
        AdvanceVendorOrderRequest $request,
        VendorOrder $vendorOrder,
    ): RedirectResponse {
        $user = $request->user();
        $user->loadMissing(['roles', 'vendor']);
        abort_unless($user->canAccessVendorPanel(), 404);

        // Own-vendor only: fail closed as 404 (do not leak existence to other vendors).
        if ($user->vendor?->id !== $vendorOrder->vendor_id) {
            abort(404);
        }

        $target = $request->targetStatus();

        try {
            $this->lifecycle->transition($user, $vendorOrder, $target);
        } catch (VendorOrderLifecycleException $e) {
            if ($e->errorCode === VendorOrderLifecycleException::UNAUTHORIZED) {
                abort(404);
            }

            return redirect()
                ->route('vendor.orders.show', $vendorOrder)
                ->withErrors([
                    'status' => __('This order cannot be advanced to that status.'),
                ]);
        }

        return redirect()
            ->route('vendor.orders.show', $vendorOrder)
            ->with('status', $this->flashFor($target));
    }

    private function actionLabel(?VendorOrderStatus $next): ?string
    {
        return match ($next) {
            VendorOrderStatus::Confirmed => __('Confirm'),
            VendorOrderStatus::Shipped => __('Mark shipped'),
            VendorOrderStatus::Delivered => __('Mark delivered'),
            default => null,
        };
    }

    private function flashFor(VendorOrderStatus $target): string
    {
        return match ($target) {
            VendorOrderStatus::Confirmed => __('Order confirmed.'),
            VendorOrderStatus::Shipped => __('Order marked as shipped.'),
            VendorOrderStatus::Delivered => __('Order marked as delivered.'),
            default => __('Order updated.'),
        };
    }
}
