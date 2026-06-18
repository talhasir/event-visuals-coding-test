<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $event = $this->route('event');
        $eventId = $event instanceof Event ? $event->id : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                // Block double-registration for the same event up front so we
                // can show a friendly message instead of hitting the unique index.
                Rule::unique('attendees', 'email')->where(
                    fn ($query) => $query->where('event_id', $eventId),
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'That email is already registered for this event.',
        ];
    }
}
