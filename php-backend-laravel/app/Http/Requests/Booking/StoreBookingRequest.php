<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Form request for creating a new booking.
 */
final class StoreBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'eventId'     => ['required', 'integer', 'exists:events,id'],
            'quantity'    => ['required', 'integer', 'min:1'],
            'seatNumbers' => ['nullable', 'array'],
            'couponCode'  => ['nullable', 'string'],
            'discount'    => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Handle a failed validation attempt by throwing a JSON-friendly exception.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException($validator, response()->json([
            'message' => 'Validation failed.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
