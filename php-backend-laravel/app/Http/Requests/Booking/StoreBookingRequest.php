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
            'eventId'              => ['required', 'integer', 'exists:events,id'],
            // Cart shape: a list of {ticketTypeId, quantity} lines.
            'items'                => ['nullable', 'array', 'min:1'],
            'items.*.ticketTypeId' => ['required_with:items', 'integer', 'exists:ticket_types,id'],
            'items.*.quantity'     => ['required_with:items', 'integer', 'min:1'],
            // Legacy single-line shape (still accepted).
            'quantity'             => ['nullable', 'integer', 'min:1'],
            'ticketTypeId'         => ['nullable', 'integer', 'exists:ticket_types,id'],
            'seatNumbers'          => ['nullable', 'array'],
            'couponCode'           => ['nullable', 'string'],
            'discount'             => ['nullable', 'numeric', 'min:0'],
            // Who the ticket is for. Nullable so older app builds don't 422 — the
            // service falls back to the account when a field is absent.
            'contact'              => ['nullable', 'array'],
            'contact.name'         => ['nullable', 'string', 'max:120'],
            'contact.email'        => ['nullable', 'email', 'max:255'],
            'contact.phone'        => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * The order's contact details, as the service expects them.
     *
     * @return array{name: string|null, email: string|null, phone: string|null}
     */
    public function contact(): array
    {
        return [
            'name'  => $this->input('contact.name'),
            'email' => $this->input('contact.email'),
            'phone' => $this->input('contact.phone'),
        ];
    }

    /**
     * A booking needs either a cart (`items`) or a legacy `quantity`. Enforce that
     * at least one path is present so the service always has a well-formed order.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $hasItems    = is_array($this->input('items')) && $this->input('items') !== [];
            $hasQuantity = $this->filled('quantity');

            if (! $hasItems && ! $hasQuantity) {
                $v->errors()->add('items', 'Provide either a cart of items or a quantity.');
            }
        });
    }

    /**
     * Normalise both shapes into a single list of order lines the service consumes.
     *
     * @return list<array{ticketTypeId: int|null, quantity: int}>
     */
    public function orderLines(): array
    {
        $items = $this->input('items');

        if (is_array($items) && $items !== []) {
            return array_values(array_map(static fn (array $i): array => [
                'ticketTypeId' => (int) $i['ticketTypeId'],
                'quantity'     => (int) $i['quantity'],
            ], $items));
        }

        return [[
            'ticketTypeId' => $this->filled('ticketTypeId') ? (int) $this->input('ticketTypeId') : null,
            'quantity'     => (int) $this->input('quantity', 1),
        ]];
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
