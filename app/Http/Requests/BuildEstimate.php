<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuildEstimate extends FormRequest
{
    public function rules(): array
    {
        $catalog = config('quote');

        return [
            'mode' => ['required', Rule::in(['guided', 'direct'])],
            'goal' => ['required_if:mode,guided', 'nullable', Rule::in(array_keys($catalog['goals']))],
            'service' => ['required_if:mode,direct', 'nullable', Rule::in(array_keys($catalog['services']))],
            'appointment' => ['nullable', Rule::in(array_keys($catalog['appointment_answers']))],
            'installation' => ['nullable', Rule::in(array_keys($catalog['installations']))],
            'addons' => ['sometimes', 'array:'.implode(',', array_keys($catalog['addons']))],
            'addons.*' => ['array:state,quantity'],
            'addons.*.state' => ['required', Rule::in(['selected', 'later', 'removed'])],
            'addons.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'budget' => ['nullable', 'string', 'max:80'],
            'timing' => ['nullable', 'string', 'max:80'],
            'features' => ['sometimes', 'array', 'max:12'],
            'features.*' => ['distinct', Rule::in(array_keys($catalog['feature_choices']))],
            'staff_count' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'sms_source' => ['nullable', Rule::in(['booking', 'calendar', 'other_event', 'already_sends', 'unknown'])],
            'booking_tool' => ['nullable', Rule::in(['supported', 'check'])],
            'email_purpose' => ['nullable', Rule::in(['inquiry', 'other'])],
            'monthly' => ['sometimes', 'array', 'max:20'],
            'monthly.*' => ['string', 'max:40'],
            'priority' => ['sometimes', 'boolean'],
            'priority_later' => ['sometimes', 'boolean'],
        ];
    }
}
