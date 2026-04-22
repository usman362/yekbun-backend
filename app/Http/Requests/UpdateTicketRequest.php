<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'event_id' => 'nullable|exists:events,id',
            'name' => 'nullable',
            'description' => 'nullable',
            'price' => 'nullable',
            'price_male' => 'nullable',
            'price_female' => 'nullable',
            'price_kids' => 'nullable',
            'quantity' => 'nullable',
            'total_sales' => 'nullable',
            'start_sale' => 'nullable',
            'end_sale' => 'nullable',
            'status' => 'nullable',
        ];
    }
}
