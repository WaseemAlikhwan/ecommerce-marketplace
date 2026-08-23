<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['rate_bps'])]
class CommissionSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_bps' => 'integer',
        ];
    }

    public static function currentRateBps(): ?int
    {
        $rate = static::query()->orderByDesc('id')->value('rate_bps');

        return $rate === null ? null : (int) $rate;
    }
}
