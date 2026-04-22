<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDonationRequest extends FormRequest
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
            'title' => 'required',
            'description' => 'nullable',
            'organization_id' => 'nullable|exists:organizations,id',
            'tags' => 'nullable',
            'start_date' => 'nullable',
            'end_date' => 'nullable',
            'status' => 'nullable|integer',
        ];
    }
}
