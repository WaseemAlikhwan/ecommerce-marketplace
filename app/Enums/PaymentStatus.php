<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Collected = 'collected';
    case Cancelled = 'cancelled';
}
