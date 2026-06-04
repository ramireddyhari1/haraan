<?php

declare(strict_types=1);

namespace App\Http\Requests\Event;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Form request for updating an existing event (partial update).
 */
final class UpdateEventRequest extends FormRequest
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
            'title'         => ['nullable', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'category'      => ['nullable', 'string'],
            'bookingFormat' => ['nullable', 'string'],
            'visibility'    => ['nullable', 'string'],
            'location'      => ['nullable', 'string'],
            'venue'         => ['nullable', 'string'],
            'date'          => ['nullable', 'date'],
            'time'          => ['nullable', 'string'],
            'price'         => ['nullable', 'numeric', 'min:0'],
            'totalSlots'    => ['nullable', 'integer', 'min:0'],
            'images'        => ['nullable', 'array'],
            'status'        => ['nullable', 'string'],
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
