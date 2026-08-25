<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'preferred_locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function vendor(): HasOne
    {
        return $this->hasOne(Vendor::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function parentOrders(): HasMany
    {
        return $this->hasMany(ParentOrder::class);
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function productReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function vendorApplications(): HasMany
    {
        return $this->hasMany(VendorApplication::class);
    }

    public function latestVendorApplication(): HasOne
    {
        return $this->hasOne(VendorApplication::class)->latestOfMany();
    }

    public function hasRole(string $role): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(fn (Role $model): bool => $model->name === $role);
        }

        return $this->roles()->where('name', $role)->exists();
    }

    public function assignRole(string $role): void
    {
        $roleModel = Role::query()->firstOrCreate(['name' => $role]);

        $this->roles()->syncWithoutDetaching([$roleModel->id]);
        $this->unsetRelation('roles');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN) || $this->isSuperAdmin();
    }

    public function isVendor(): bool
    {
        return $this->hasRole(Role::VENDOR);
    }

    public function isCustomer(): bool
    {
        return $this->hasRole(Role::CUSTOMER);
    }

    public function isStaff(): bool
    {
        return $this->isAdmin();
    }

    public function canAccessVendorPanel(): bool
    {
        return $this->isVendor()
            && $this->vendor !== null
            && $this->vendor->canAccessPanel();
    }

    public function preferredLocale(): string
    {
        return $this->preferred_locale ?: 'ar';
    }
}
