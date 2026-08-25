<?php

namespace App\Services;

use App\Enums\ParentOrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Exceptions\OrderCancellationException;
use App\Models\ParentOrder;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VendorOrder;
use App\Notifications\OrderCancelledCustomerNotification;
use App\Notifications\OrderCancelledVendorNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Authoritative Parent / Vendor Order cancellations after COD placement (CAN-A).
 */
class OrderCancellationService
{
    public function __construct(
        private readonly CouponService $coupons,
    ) {}

    /**
     * Customer cancels own Parent Order while every Vendor Order is still pending.
     */
    public function cancelParentByCustomer(User $actor, ParentOrder $parentOrder): ParentOrder
    {
        if (! $this->actorOwnsParentOrder($actor, $parentOrder)) {
            throw OrderCancellationException::unauthorized();
        }

        $cancelledVendorOrders = DB::transaction(function () use ($parentOrder): array {
            /** @var ParentOrder $lockedParent */
            $lockedParent = ParentOrder::query()
                ->whereKey($parentOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedParent->status === ParentOrderStatus::Cancelled) {
                throw OrderCancellationException::illegalState();
            }

            $vendorOrders = VendorOrder::query()
                ->where('parent_order_id', $lockedParent->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($vendorOrders->isEmpty()) {
                throw OrderCancellationException::illegalState();
            }

            foreach ($vendorOrders as $vendorOrder) {
                if ($vendorOrder->status !== VendorOrderStatus::Pending) {
                    throw OrderCancellationException::illegalState();
                }
            }

            $cancelled = [];
            foreach ($vendorOrders as $vendorOrder) {
                $cancelled[] = $this->cancelVendorOrderLocked($vendorOrder);
            }

            $lockedParent->status = ParentOrderStatus::Cancelled;
            $lockedParent->save();

            $this->coupons->releaseForParentOrder($lockedParent);

            return $cancelled;
        });

        $parent = $parentOrder->fresh(['user', 'vendorOrders.vendor.user']) ?? $parentOrder;

        $this->notifyParentCancelled($parent, $cancelledVendorOrders);

        return $parent;
    }

    /**
     * Vendor cancels own Vendor Order while pending or confirmed.
     */
    public function cancelVendorOrderByVendor(User $actor, VendorOrder $vendorOrder): VendorOrder
    {
        if (! $this->actorOwnsVendorOrder($actor, $vendorOrder)) {
            throw OrderCancellationException::unauthorized();
        }

        $cancelled = DB::transaction(function () use ($vendorOrder): VendorOrder {
            /** @var VendorOrder $locked */
            $locked = VendorOrder::query()
                ->whereKey($vendorOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, [VendorOrderStatus::Pending, VendorOrderStatus::Confirmed], true)) {
                throw OrderCancellationException::illegalState();
            }

            $cancelledVo = $this->cancelVendorOrderLocked($locked);

            /** @var ParentOrder $parent */
            $parent = ParentOrder::query()
                ->whereKey($cancelledVo->parent_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $remainingActive = VendorOrder::query()
                ->where('parent_order_id', $parent->id)
                ->where('status', '!=', VendorOrderStatus::Cancelled->value)
                ->exists();

            if (! $remainingActive && $parent->status !== ParentOrderStatus::Cancelled) {
                $parent->status = ParentOrderStatus::Cancelled;
                $parent->save();
            }

            $this->coupons->releaseAfterVendorOrderCancelled($cancelledVo);

            return $cancelledVo->fresh(['parentOrder.user', 'vendor.user', 'payment', 'items']) ?? $cancelledVo;
        });

        $this->notifyVendorOrderCancelled($cancelled);

        return $cancelled;
    }

    public function customerCanCancelParent(ParentOrder $parentOrder): bool
    {
        if ($parentOrder->status === ParentOrderStatus::Cancelled) {
            return false;
        }

        $statuses = $parentOrder->relationLoaded('vendorOrders')
            ? $parentOrder->vendorOrders->pluck('status')
            : $parentOrder->vendorOrders()->pluck('status');

        if ($statuses->isEmpty()) {
            return false;
        }

        return $statuses->every(
            fn ($status): bool => ($status instanceof VendorOrderStatus ? $status : VendorOrderStatus::from((string) $status)) === VendorOrderStatus::Pending
        );
    }

    public function vendorCanCancelVendorOrder(VendorOrder $vendorOrder): bool
    {
        return in_array($vendorOrder->status, [VendorOrderStatus::Pending, VendorOrderStatus::Confirmed], true);
    }

    private function cancelVendorOrderLocked(VendorOrder $locked): VendorOrder
    {
        $locked->loadMissing(['items', 'payment']);

        $this->restoreStockForVendorOrder($locked);
        $this->cancelPendingPayment($locked);

        $locked->status = VendorOrderStatus::Cancelled;
        $locked->save();

        return $locked;
    }

    private function restoreStockForVendorOrder(VendorOrder $vendorOrder): void
    {
        $variantIds = $vendorOrder->items
            ->pluck('variant_id')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($variantIds === []) {
            return;
        }

        /** @var Collection<int, ProductVariant> $variants */
        $variants = ProductVariant::withTrashed()
            ->whereIn('id', $variantIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($vendorOrder->items as $item) {
            $variantId = $item->variant_id;
            if ($variantId === null) {
                continue;
            }

            $variant = $variants->get($variantId);
            if ($variant === null) {
                continue;
            }

            $restored = (int) $variant->quantity + (int) $item->quantity;
            if ($restored > ProductVariant::MAX_QUANTITY) {
                $restored = ProductVariant::MAX_QUANTITY;
            }

            $variant->forceFill(['quantity' => $restored])->save();
        }
    }

    private function cancelPendingPayment(VendorOrder $vendorOrder): void
    {
        $payment = $vendorOrder->payment;
        if ($payment === null) {
            /** @var Payment|null $payment */
            $payment = Payment::query()
                ->where('vendor_order_id', $vendorOrder->id)
                ->lockForUpdate()
                ->first();
        } else {
            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->first();
        }

        if ($payment === null) {
            return;
        }

        // Never mutate collected payments in CAN V1.
        if ($payment->status === PaymentStatus::Collected) {
            throw OrderCancellationException::illegalState();
        }

        if ($payment->status === PaymentStatus::Cancelled) {
            return;
        }

        $payment->status = PaymentStatus::Cancelled;
        $payment->save();
    }

    private function actorOwnsParentOrder(User $actor, ParentOrder $parentOrder): bool
    {
        return $actor->isCustomer()
            && (int) $actor->id === (int) $parentOrder->user_id;
    }

    private function actorOwnsVendorOrder(User $actor, VendorOrder $vendorOrder): bool
    {
        if (! $actor->canAccessVendorPanel()) {
            return false;
        }

        $vendorId = $actor->vendor?->id;

        return $vendorId !== null && (int) $vendorId === (int) $vendorOrder->vendor_id;
    }

    /**
     * @param  list<VendorOrder>  $cancelledVendorOrders
     */
    private function notifyParentCancelled(ParentOrder $parentOrder, array $cancelledVendorOrders): void
    {
        $parentOrder->loadMissing(['user', 'vendorOrders.vendor.user']);

        $customer = $parentOrder->user;
        if ($customer !== null) {
            $customer->notify(new OrderCancelledCustomerNotification($parentOrder));
        }

        foreach ($cancelledVendorOrders as $vendorOrder) {
            $vendorOrder->loadMissing(['vendor.user']);
            $vendorUser = $vendorOrder->vendor?->user;
            if ($vendorUser !== null) {
                $vendorUser->notify(new OrderCancelledVendorNotification($vendorOrder));
            }
        }
    }

    private function notifyVendorOrderCancelled(VendorOrder $vendorOrder): void
    {
        $vendorOrder->loadMissing(['parentOrder.user', 'vendor.user']);

        $customer = $vendorOrder->parentOrder?->user;
        if ($customer !== null && $vendorOrder->parentOrder !== null) {
            $customer->notify(new OrderCancelledCustomerNotification(
                $vendorOrder->parentOrder,
                $vendorOrder,
            ));
        }

        $vendorUser = $vendorOrder->vendor?->user;
        if ($vendorUser !== null) {
            $vendorUser->notify(new OrderCancelledVendorNotification($vendorOrder));
        }
    }
}
