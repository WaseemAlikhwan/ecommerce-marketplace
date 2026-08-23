<?php

namespace App\Exceptions;

use RuntimeException;

class VendorApplicationException extends RuntimeException
{
    public static function unverifiedEmail(): self
    {
        return new self(__('Verify your email before applying as a vendor.'));
    }

    public static function alreadyVendor(): self
    {
        return new self(__('You already have a vendor account.'));
    }

    public static function pendingExists(): self
    {
        return new self(__('You already have a pending vendor application.'));
    }

    public static function notPending(): self
    {
        return new self(__('This application is no longer pending.'));
    }
}
