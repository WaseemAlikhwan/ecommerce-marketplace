<?php

namespace App\Enums;

enum CouponScope: string
{
    case Platform = 'platform';
    case Vendor = 'vendor';
}
