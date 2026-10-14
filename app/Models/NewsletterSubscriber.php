<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Inscrito na newsletter (double opt-in): so entra na lista depois de clicar
 * no link do e-mail de confirmacao (ADR-024).
 *
 * @property int $id
 * @property string $email
 * @property string $token
 * @property string $locale
 * @property string $source
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $unsubscribed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['email', 'token', 'locale', 'source'])]
class NewsletterSubscriber extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /**
     * Token do link de confirmacao e de descadastro (64 caracteres).
     */
    public static function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null && $this->unsubscribed_at === null;
    }
}
