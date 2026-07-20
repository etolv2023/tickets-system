<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Subscribing a browser is subscribing your own browser; there is no
        // object to authorise against beyond being logged in.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Only the real push services, so this can't be turned into an
            // open relay that POSTs to an arbitrary host on our behalf.
            'endpoint' => ['required', 'url', 'max:2048', 'starts_with:https://'],
            'keys.p256dh' => ['nullable', 'string', 'max:255'],
            'keys.auth' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['endpoint' => 'عنوان الاشتراك'];
    }
}
