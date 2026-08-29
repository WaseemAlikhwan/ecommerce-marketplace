<?php

namespace App\Services;

use App\Models\CustomerAddress;
use App\Models\Governorate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CustomerAddressService
{
    /**
     * @return list<array{id: int, name: string, cities: list<array{id: int, name: string}>}>
     */
    public function governoratesForLocale(string $locale): array
    {
        return Governorate::query()
            ->with(['cities' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
            ->inSyria()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Governorate $governorate) use ($locale): array {
                return [
                    'id' => (int) $governorate->id,
                    'name' => $locale === 'en' ? $governorate->name_en : $governorate->name_ar,
                    'cities' => $governorate->cities->map(fn ($city): array => [
                        'id' => (int) $city->id,
                        'name' => $locale === 'en' ? $city->name_en : $city->name_ar,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array{label?: string|null, recipient_name: string, phone: string, governorate_id: int, city_id: int, line1: string, line2?: string|null, notes?: string|null, is_default?: bool}  $data
     */
    public function create(User $user, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($user, $data): CustomerAddress {
            $makeDefault = $this->shouldBeDefault($user, (bool) ($data['is_default'] ?? false));

            if ($makeDefault) {
                $this->clearDefaultForUser((int) $user->id);
            }

            return CustomerAddress::query()->create([
                'user_id' => $user->id,
                'label' => $data['label'] ?? null,
                'recipient_name' => $data['recipient_name'],
                'phone' => $data['phone'],
                'governorate_id' => (int) $data['governorate_id'],
                'city_id' => (int) $data['city_id'],
                'line1' => $data['line1'],
                'line2' => $data['line2'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_default' => $makeDefault,
            ]);
        });
    }

    /**
     * @param  array{label?: string|null, recipient_name: string, phone: string, governorate_id: int, city_id: int, line1: string, line2?: string|null, notes?: string|null, is_default?: bool}  $data
     */
    public function update(CustomerAddress $address, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($address, $data): CustomerAddress {
            $wantsDefault = (bool) ($data['is_default'] ?? false);

            if ($wantsDefault) {
                $this->clearDefaultForUser((int) $address->user_id);
            }

            $address->update([
                'label' => $data['label'] ?? null,
                'recipient_name' => $data['recipient_name'],
                'phone' => $data['phone'],
                'governorate_id' => (int) $data['governorate_id'],
                'city_id' => (int) $data['city_id'],
                'line1' => $data['line1'],
                'line2' => $data['line2'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_default' => $wantsDefault,
            ]);

            return $address->fresh(['governorate', 'city']) ?? $address;
        });
    }

    public function delete(CustomerAddress $address): void
    {
        $address->delete();
    }

    public function setDefault(CustomerAddress $address): void
    {
        DB::transaction(function () use ($address): void {
            $this->clearDefaultForUser((int) $address->user_id);
            $address->update(['is_default' => true]);
        });
    }

    public function shouldBeDefault(User $user, bool $requested): bool
    {
        return $requested
            || ! CustomerAddress::query()->where('user_id', $user->id)->exists();
    }

    public function clearDefaultForUser(int $userId): void
    {
        CustomerAddress::query()
            ->where('user_id', $userId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
