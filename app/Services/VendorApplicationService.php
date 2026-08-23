<?php

namespace App\Services;

use App\Enums\StoreStatus;
use App\Enums\VendorApplicationStatus;
use App\Enums\VendorStatus;
use App\Exceptions\VendorApplicationException;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorApplication;
use App\Notifications\VendorApplicationApprovedNotification;
use App\Notifications\VendorApplicationRejectedNotification;
use App\Notifications\VendorApplicationSubmittedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VendorApplicationService
{
    public function submit(User $user, string $storeName, ?string $note = null): VendorApplication
    {
        if (! $user->hasVerifiedEmail()) {
            throw VendorApplicationException::unverifiedEmail();
        }

        if ($user->isVendor() || $user->vendor()->exists()) {
            throw VendorApplicationException::alreadyVendor();
        }

        $application = DB::transaction(function () use ($user, $storeName, $note): VendorApplication {
            $user->vendorApplications()->where('status', VendorApplicationStatus::Pending)->lockForUpdate()->get();

            if ($user->vendorApplications()->pending()->exists()) {
                throw VendorApplicationException::pendingExists();
            }

            return $user->vendorApplications()->create([
                'store_name' => $storeName,
                'note' => $note,
                'status' => VendorApplicationStatus::Pending,
                'pending_for_user_id' => $user->id,
            ]);
        });

        $this->notifyStaff(new VendorApplicationSubmittedNotification($application));

        return $application;
    }

    public function approve(VendorApplication $application, User $reviewer): Vendor
    {
        $alreadyApproved = false;

        $vendor = DB::transaction(function () use ($application, $reviewer, &$alreadyApproved): Vendor {
            /** @var VendorApplication $application */
            $application = VendorApplication::query()->lockForUpdate()->findOrFail($application->id);

            if ($application->status === VendorApplicationStatus::Approved) {
                $alreadyApproved = true;
                $existing = Vendor::query()->where('user_id', $application->user_id)->firstOrFail();

                $this->ensureStoreFor($existing, $application);

                return $existing;
            }

            if (! $application->isPending()) {
                throw VendorApplicationException::notPending();
            }

            $application->fill([
                'status' => VendorApplicationStatus::Approved,
                'pending_for_user_id' => null,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $vendor = Vendor::query()->firstOrCreate(
                ['user_id' => $application->user_id],
                ['status' => VendorStatus::Approved],
            );

            $this->ensureStoreFor($vendor, $application);

            $application->user->assignRole(Role::VENDOR);

            return $vendor->load('store');
        });

        if (! $alreadyApproved) {
            $application->refresh()->user->notify(new VendorApplicationApprovedNotification($application));
        }

        return $vendor;
    }

    public function reject(VendorApplication $application, User $reviewer, ?string $reason = null): VendorApplication
    {
        $alreadyRejected = false;

        $application = DB::transaction(function () use ($application, $reviewer, $reason, &$alreadyRejected): VendorApplication {
            /** @var VendorApplication $application */
            $application = VendorApplication::query()->lockForUpdate()->findOrFail($application->id);

            if ($application->status === VendorApplicationStatus::Rejected) {
                $alreadyRejected = true;

                return $application;
            }

            if (! $application->isPending()) {
                throw VendorApplicationException::notPending();
            }

            $application->fill([
                'status' => VendorApplicationStatus::Rejected,
                'pending_for_user_id' => null,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            return $application;
        });

        if (! $alreadyRejected) {
            $application->user->notify(new VendorApplicationRejectedNotification($application));
        }

        return $application;
    }

    private function ensureStoreFor(Vendor $vendor, VendorApplication $application): Store
    {
        $existing = $vendor->store;

        if ($existing !== null) {
            return $existing;
        }

        return $vendor->store()->create([
            'name' => $application->store_name,
            'slug' => $this->uniqueSlug($application->store_name),
            'description' => null,
            'contact_email' => $application->user->email,
            'contact_phone' => $application->user->phone,
            'status' => StoreStatus::Active,
            'rating' => 0,
            'default_currency_code' => 'SYP',
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'store';
        $slug = $base;
        $i = 1;

        while (Store::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function notifyStaff(VendorApplicationSubmittedNotification $notification): void
    {
        User::query()
            ->whereHas('roles', function ($query): void {
                $query->whereIn('name', [Role::ADMIN, Role::SUPER_ADMIN]);
            })
            ->each(fn (User $staff) => $staff->notify($notification));
    }
}
