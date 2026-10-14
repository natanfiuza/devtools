<?php

namespace App\Models;

use App\Notifications\ResetPassword as ResetPasswordNotification;
use App\Notifications\VerifyEmail as VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $locale
 * @property string|null $appearance
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'appearance', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Vinculos com provedores sociais (Google, GitHub).
     *
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Conta criada por login social nasce sem senha (ADR-023).
     */
    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    /**
     * Idioma da conta: e o que faz os e-mails (reset, verificacao) saírem no
     * idioma do usuario, e nao no idioma de quem pediu.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * E-mails proprios no lugar dos do framework, que tem os textos em ingles
     * presos no codigo (ver App\Notifications).
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }
}
