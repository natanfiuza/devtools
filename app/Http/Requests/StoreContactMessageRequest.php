<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validacao do formulario publico de contato.
 *
 * Aplica rate limit de 5 envios por minuto por IP com mensagem traduzida
 * informando os segundos restantes, e sanitiza os campos.
 */
class StoreContactMessageRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    public const VALID_SUBJECTS = [
        'general',
        'donation',
        'feedback',
        'bug',
        'other',
    ];

    protected function prepareForValidation(): void
    {
        $this->ensureIsNotRateLimited();

        foreach (['name', 'email', 'subject', 'message', 'hp_website'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);

                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'subject' => ['required', 'string', Rule::in(self::VALID_SUBJECTS)],
            'message' => ['required', 'string', 'max:3000'],
            'hp_website' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Verifica se o envio foi identificado como spam (honeypot preenchido).
     */
    public function isSpam(): bool
    {
        return $this->filled('hp_website');
    }

    /**
     * Registra uma tentativa de envio no contador de rate limit.
     */
    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), 60);
    }

    protected function ensureIsNotRateLimited(): void
    {
        $key = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'message' => __('contact.throttle', ['seconds' => $seconds]),
            ]);
        }
    }

    protected function throttleKey(): string
    {
        return 'contact_form:'.$this->ip();
    }
}
