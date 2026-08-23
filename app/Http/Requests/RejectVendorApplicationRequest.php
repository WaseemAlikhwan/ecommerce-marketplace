<?php

namespace App\Http\Requests;

use App\Models\VendorApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RejectVendorApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('vendor_application');

        return $application instanceof VendorApplication
            && ($this->user()?->can('reject', $application) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
