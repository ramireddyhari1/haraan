<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Form request for user registration.
 */
final class RegisterRequest extends FormRequest
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
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
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
