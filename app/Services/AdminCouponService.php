<?php

namespace App\Services;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Support\Facades\DB;

class AdminCouponService
{
    /**
     * @param  array{
     *     code: string,
     *     scope: string,
     *     vendor_id?: int|null,
     *     type: string,
     *     value: int,
     *     currency_code: string,
     *     starts_at?: string|null,
     *     ends_at?: string|null,
     *     min_eligible_amount_minor: int,
     *     max_discount_amount_minor?: int|null,
     *     global_usage_limit?: int|null,
     *     per_user_usage_limit?: int|null,
     *     is_active?: bool,
     *     product_ids?: list<int>,
     *     category_ids?: list<int>
     * }  $data
     */
    public function create(array $data): Coupon
    {
        return DB::transaction(function () use ($data): Coupon {
            $coupon = Coupon::query()->create($this->attributes($data));
            $this->syncRestrictions($coupon, $data);

            return $coupon->load(['vendor.store', 'products.translations', 'categories.translations']);
        });
    }

    /**
     * @param  array{
     *     code: string,
     *     scope: string,
     *     vendor_id?: int|null,
     *     type: string,
     *     value: int,
     *     currency_code: string,
     *     starts_at?: string|null,
     *     ends_at?: string|null,
     *     min_eligible_amount_minor: int,
     *     max_discount_amount_minor?: int|null,
     *     global_usage_limit?: int|null,
     *     per_user_usage_limit?: int|null,
     *     is_active?: bool,
     *     product_ids?: list<int>,
     *     category_ids?: list<int>
     * }  $data
     */
    public function update(Coupon $coupon, array $data): Coupon
    {
        return DB::transaction(function () use ($coupon, $data): Coupon {
            /** @var Coupon $coupon */
            $coupon = Coupon::query()->lockForUpdate()->findOrFail($coupon->id);

            $coupon->fill($this->attributes($data))->save();
            $this->syncRestrictions($coupon, $data);

            return $coupon->refresh()->load(['vendor.store', 'products.translations', 'categories.translations']);
        });
    }

    public function setActive(Coupon $coupon, bool $active): Coupon
    {
        $coupon->is_active = $active;
        $coupon->save();

        return $coupon->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $scope = CouponScope::from((string) $data['scope']);

        return [
            'code' => strtoupper(trim((string) $data['code'])),
            'scope' => $scope,
            'vendor_id' => $scope === CouponScope::Vendor
                ? (int) $data['vendor_id']
                : null,
            'type' => CouponType::from((string) $data['type']),
            'value' => (int) $data['value'],
            'currency_code' => (string) $data['currency_code'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'min_eligible_amount_minor' => (int) ($data['min_eligible_amount_minor'] ?? 0),
            'max_discount_amount_minor' => array_key_exists('max_discount_amount_minor', $data)
                ? ($data['max_discount_amount_minor'] !== null ? (int) $data['max_discount_amount_minor'] : null)
                : null,
            'global_usage_limit' => array_key_exists('global_usage_limit', $data)
                ? ($data['global_usage_limit'] !== null ? (int) $data['global_usage_limit'] : null)
                : null,
            'per_user_usage_limit' => array_key_exists('per_user_usage_limit', $data)
                ? ($data['per_user_usage_limit'] !== null ? (int) $data['per_user_usage_limit'] : null)
                : null,
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : true,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRestrictions(Coupon $coupon, array $data): void
    {
        $coupon->products()->sync(array_values(array_map('intval', $data['product_ids'] ?? [])));
        $coupon->categories()->sync(array_values(array_map('intval', $data['category_ids'] ?? [])));
    }
}
