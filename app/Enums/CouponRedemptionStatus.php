<?php

namespace App\Enums;

enum CouponRedemptionStatus: string
{
    case Active = 'active';
    case Released = 'released';
}
