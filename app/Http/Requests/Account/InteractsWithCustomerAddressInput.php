<?php

namespace App\Http\Requests\Account;

trait InteractsWithCustomerAddressInput
{
    protected function wantsDefaultAddress(): bool
    {
        if (! array_key_exists('is_default', $this->all())) {
            return false;
        }

        $value = $this->input('is_default');
        if (is_array($value)) {
            $value = end($value);
        }

        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }
}
