<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const CUSTOMER = 'customer';

    public const VENDOR = 'vendor';

    public const ADMIN = 'admin';

    public const SUPER_ADMIN = 'super_admin';

    /**
     * @var list<string>
     */
    public const ALL = [
        self::CUSTOMER,
        self::VENDOR,
        self::ADMIN,
        self::SUPER_ADMIN,
    ];

    protected $fillable = [
        'name',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
