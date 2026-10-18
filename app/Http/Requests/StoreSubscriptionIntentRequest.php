<?php

namespace App\Http\Requests;

use App\Concerns\ProfileValidationRules;
use App\Enums\SubscriptionIntentPaymentMethod;
use App\Models\Plan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionIntentRequest extends FormRequest
{
    use ProfileValidationRules;

    protected function prepareForValidation(): void
    {
        if ($this->has('username') && is_string($this->input('username'))) {
            $this->merge([
                'username' => strtolower(ltrim(trim($this->input('username')), '@')),
            ]);
        }

        if ($this->has('phone') && is_string($this->input('phone'))) {
            $phone = trim($this->input('phone'));
            $countryCode = $this->input('country_code');

            if (is_string($countryCode) && $countryCode !== '' && ! str_starts_with($phone, '+')) {
                $phone = trim($countryCode).' '.$phone;
            }

            $this->merge([
                'phone' => $phone,
            ]);
        }

        if ($this->has('document')) {
            $document = is_string($this->input('document')) ? trim($this->input('document')) : null;

            $this->merge([
                'document' => $document === '' ? null : $document,
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan' => [
                'required',
                'string',
                Rule::exists(Plan::class, 'slug')
                    ->where('active', true)
                    ->where(fn ($query) => $query->where('price_cents', '>', 0)),
            ],
            'username' => $this->usernameRules($this->user()?->id, required: true),
            'phone' => ['required', 'string', 'min:8', 'max:32'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'document' => ['nullable', 'string', 'max:32'],
            'payment_method' => ['required', Rule::enum(SubscriptionIntentPaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
