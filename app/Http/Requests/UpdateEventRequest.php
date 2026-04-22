<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
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
            'title' => 'nullable',
            'description' => 'nullable',
            'event_category_id' => 'nullable',
            'user_id' => 'nullable',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'country' => 'nullable',
            'state' => 'nullable',
            'city' => 'nullable',
            'zipcode' => 'nullable',
            'address' => 'nullable',
            'status' => 'nullable',
            'image' => 'nullable',
            'sound' => 'nullable',

            'promoter_first_name' => 'nullable',
            'promoter_last_name' => 'nullable',
            'promoter_email' => 'nullable',
            'promoter_phone_number' => 'nullable',
            'promoter_rojava_name' => 'nullable',
            'promoter_rojava_id' => 'nullable',

            'ticket_sale' => 'nullable',
            'online_sale' => 'nullable',
            'price' => 'nullable',
            'price_male' => 'nullable',
            'price_male_notification' => 'nullable',
            'price_female' => 'nullable',
            'price_female_notification' => 'nullable',
            'price_kids' => 'nullable',
            'price_kids_notification' => 'nullable',
        ];
    }
}
