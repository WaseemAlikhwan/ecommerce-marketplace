<?php

namespace App\Http\Requests\Account;

use App\Models\ParentOrder;
use Illuminate\Foundation\Http\FormRequest;

class CancelParentOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ParentOrder|null $parentOrder */
        $parentOrder = $this->route('parentOrder');

        return $parentOrder instanceof ParentOrder
            && ($this->user()?->can('cancel', $parentOrder) ?? false);
    }

    protected function failedAuthorization(): void
    {
        abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
