<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\ParentOrder;
use App\Models\WishlistItem;
use App\Services\OrderViewService;
use App\Services\WishlistService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly OrderViewService $orderViews,
        private readonly WishlistService $wishlists,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $addressCount = 0;
        $wishlistCount = 0;
        $recentOrders = [];

        if ($user !== null && $user->isCustomer()) {
            $addressCount = CustomerAddress::query()
                ->where('user_id', $user->id)
                ->count();

            if ($user->can('viewAny', WishlistItem::class)) {
                $wishlistCount = $this->wishlists->countFor($user);
            }

            $orders = ParentOrder::query()
                ->where('user_id', $user->id)
                ->with(['vendorOrders.payment'])
                ->latest('placed_at')
                ->latest('id')
                ->limit(5)
                ->get();

            $recentOrders = $this->orderViews->parentIndexRows(
                $orders,
                app()->getLocale(),
            );
        }

        return view('dashboard', [
            'addressCount' => $addressCount,
            'wishlistCount' => $wishlistCount,
            'recentOrders' => $recentOrders,
        ]);
    }
}
