<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => ['required', 'in:ios,android'],
            'receipt' => ['nullable'],
            'type' => ['required', 'in:subs,in-app'],
            'product_id' => ['required', 'string'],
            'transaction_id' => ['nullable', 'string'],
            'transaction_receipt' => ['nullable', 'string'],
            'purchase_token' => ['nullable', 'string'],
        ];
    }
}

