<?php

namespace App\Enums;

enum ParentOrderStatus: string
{
    case Placed = 'placed';
    case Cancelled = 'cancelled';
}
