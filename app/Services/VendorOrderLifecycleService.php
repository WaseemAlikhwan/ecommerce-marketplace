<?php

namespace App\Services;

use App\Enums\VendorOrderStatus;
use App\Exceptions\VendorOrderLifecycleException;
use App\Models\User;
use App\Models\VendorOrder;
use App\Notifications\VendorOrderStatusChangedCustomerNotification;
use App\Notifications\VendorOrderStatusChangedVendorNotification;
use Illuminate\Support\Facades\DB;

/**
 * Authoritative Vendor Order fulfillment transitions after COD placement (VOL-A).
 */
class VendorOrderLifecycleService
{
    /**
     * @var array<string, string>
     */
    private const ALLOWED = [
        VendorOrderStatus::Pending->value => VendorOrderStatus::Confirmed->value,
        VendorOrderStatus::Confirmed->value => VendorOrderStatus::Shipped->value,
        VendorOrderStatus::Shipped->value => VendorOrderStatus::Delivered->value,
    ];

    public function confirm(User $actor, VendorOrder $vendorOrder): VendorOrder
    {
        return $this->transition($actor, $vendorOrder, VendorOrderStatus::Confirmed);
    }

    public function ship(User $actor, VendorOrder $vendorOrder): VendorOrder
    {
        return $this->transition($actor, $vendorOrder, VendorOrderStatus::Shipped);
    }

    public function deliver(User $actor, VendorOrder $vendorOrder): VendorOrder
    {
        return $this->transition($actor, $vendorOrder, VendorOrderStatus::Delivered);
    }

    public function transition(User $actor, VendorOrder $vendorOrder, VendorOrderStatus $target): VendorOrder
    {
        if (! $this->actorOwnsVendorOrder($actor, $vendorOrder)) {
            throw VendorOrderLifecycleException::unauthorized();
        }

        $updated = DB::transaction(function () use ($vendorOrder, $target): VendorOrder {
            /** @var VendorOrder $locked */
            $locked = VendorOrder::query()
                ->whereKey($vendorOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            $from = $locked->status;
            $expected = self::ALLOWED[$from->value] ?? null;

            if ($expected !== $target->value) {
                throw VendorOrderLifecycleException::illegalTransition();
            }

            $locked->status = $target;

            if ($target === VendorOrderStatus::Delivered && $locked->commission_recognized_at === null) {
                $locked->commission_recognized_at = now();
            }

            $locked->save();

            return $locked->fresh(['parentOrder.user', 'vendor.user', 'payment']) ?? $locked;
        });

        $this->notify($updated, $target);

        return $updated;
    }

    public function canTransition(VendorOrderStatus $from, VendorOrderStatus $to): bool
    {
        return (self::ALLOWED[$from->value] ?? null) === $to->value;
    }

    public function nextStatus(VendorOrderStatus $from): ?VendorOrderStatus
    {
        $next = self::ALLOWED[$from->value] ?? null;

        return $next !== null ? VendorOrderStatus::from($next) : null;
    }

    private function actorOwnsVendorOrder(User $actor, VendorOrder $vendorOrder): bool
    {
        if (! $actor->canAccessVendorPanel()) {
            return false;
        }

        $vendorId = $actor->vendor?->id;

        return $vendorId !== null && (int) $vendorId === (int) $vendorOrder->vendor_id;
    }

    private function notify(VendorOrder $vendorOrder, VendorOrderStatus $status): void
    {
        $vendorOrder->loadMissing(['parentOrder.user', 'vendor.user']);

        $customer = $vendorOrder->parentOrder?->user;
        if ($customer !== null) {
            $customer->notify(new VendorOrderStatusChangedCustomerNotification($vendorOrder, $status));
        }

        $vendorUser = $vendorOrder->vendor?->user;
        if ($vendorUser !== null) {
            $vendorUser->notify(new VendorOrderStatusChangedVendorNotification($vendorOrder, $status));
        }
    }
}
