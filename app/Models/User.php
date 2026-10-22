<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Notifications\ResetPassword as ResetPasswordNotification;
use App\Notifications\VerifyEmail as VerifyEmailNotification;
use App\Support\Locales;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property UserRole $role
 * @property Carbon|null $suspended_at
 * @property Carbon|null $anonymized_at
 * @property string|null $locale
 * @property string|null $appearance
 * @property bool $history_enabled
 * @property string|null $remember_token
 * @property string|null $photo_uuid
 * @property-read string|null $avatar
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property-read bool $two_factor_enabled
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'username', 'email', 'password', 'photo_uuid', 'history_enabled'])]
#[Hidden(['password', 'appearance', 'remember_token', 'role', 'suspended_at', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, \Illuminate\Database\Eloquent\SoftDeletes, \Laravel\Fortify\TwoFactorAuthenticatable, Notifiable;

    /**
     * @var list<string>
     */
    protected $appends = [
        'avatar',
        'two_factor_enabled',
    ];

    /**
     * Conta nova nasce usuario comum - o mesmo default da coluna, repetido aqui
     * para o objeto recem-criado ja ter o papel em memoria (e nao null) antes
     * de voltar do banco.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'user',
    ];

    /**
     * URL publica da foto de perfil, quando houver.
     */
    public function getAvatarAttribute(): ?string
    {
        return $this->photo_uuid !== null
            ? route('users.profile.photo', ['uuid' => $this->photo_uuid])
            : null;
    }

    /**
     * Indica se a conta possui autenticacao em dois fatores ativada e confirmada.
     */
    public function getTwoFactorEnabledAttribute(): bool
    {
        return $this->hasEnabledTwoFactorAuthentication();
    }

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
            'role' => UserRole::class,
            'suspended_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'history_enabled' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Papel de admin. `role` fica fora do #[Fillable] e do payload do frontend:
     * quem escreve e o comando `devtools:admin` ou a tela de contas, e o que o
     * frontend precisa saber vai como `auth.isAdmin` (ADR-025).
     */
    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    /**
     * Conta suspensa: nao entra (nem por senha, nem por login social) e a
     * sessao aberta cai no middleware EnsureAccountIsActive. A suspensao nao
     * apaga nada - o dado e a assinatura continuam, so o acesso para.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Conta excluida pela pessoa (ou pelo painel), que continua no banco como
     * linha sem dono por causa do registro fiscal (ADR-028). O nome e o e-mail
     * ja sairam: quem escreve na tela usa esta marca para dizer "conta
     * removida" no idioma de quem esta lendo.
     */
    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    /**
     * Contas em janela de carencia de 30 dias para expurgo definitivo (ADR-035).
     *
     * @param  Builder<static>  $query
     */
    public function scopeInDeletionGracePeriod(Builder $query): void
    {
        $query->onlyTrashed()->where('deleted_at', '>', now()->subDays(30));
    }

    /**
     * Contas anonimizadas pelo expurgo ou pelo usuario (ADR-028).
     *
     * @param  Builder<static>  $query
     */
    public function scopeAnonymized(Builder $query): void
    {
        $query->withTrashed()->whereNotNull('anonymized_at');
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
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * A assinatura que vale agora - no maximo uma. A vigencia e resolvida na
     * leitura (`scopeCurrent`) porque o deploy nao roda `schedule:run`.
     *
     * O plano vem junto: quem chama quase sempre precisa dele, e sem o eager
     * load isso viraria uma consulta por conta listada no painel.
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->current()
            ->with('plan')
            ->orderByDesc('current_period_start')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * O proximo periodo empilhado, ainda no futuro.
     *
     * Um pagamento antecipado abre uma linha que comeca quando a atual
     * termina; ela nao vale agora, mas a tela de faturas precisa mostra-la para
     * a conta saber que a renovacao ja esta paga.
     */
    public function nextSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->where('status', SubscriptionStatus::Active->value)
            ->whereNotNull('current_period_start')
            ->where('current_period_start', '>', now())
            ->with('plan')
            ->orderBy('current_period_start')
            ->orderBy('id')
            ->first();
    }

    /**
     * Plano efetivo da conta: a assinatura vigente ou, sem ela, o Free - que e
     * o piso de todo mundo.
     */
    public function effectivePlan(): Plan
    {
        $subscription = $this->activeSubscription();

        if ($subscription === null) {
            return Plan::free();
        }

        return $subscription->plan;
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<UsageLedger, $this>
     */
    public function usageLedger(): HasMany
    {
        return $this->hasMany(UsageLedger::class);
    }

    /**
     * Solicitacoes do AI SQL Builder feitas por esta conta.
     *
     * @return HasMany<AiRequest, $this>
     */
    public function aiRequests(): HasMany
    {
        return $this->hasMany(AiRequest::class);
    }

    /**
     * @return HasMany<SubscriptionIntent, $this>
     */
    public function subscriptionIntents(): HasMany
    {
        return $this->hasMany(SubscriptionIntent::class);
    }

    /**
     * Checkouts pagos iniciados por esta conta, aprovados ou nao (F6).
     *
     * @return HasMany<CheckoutSession, $this>
     */
    public function checkoutSessions(): HasMany
    {
        return $this->hasMany(CheckoutSession::class);
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
     *
     * Sem idioma na conta, vale o fallback do app - o mesmo que
     * App\Notifications\* usa no link. A conta e o idioma do texto e do link
     * saem sempre iguais, mesmo com o e-mail saindo da fila (onde nao existe
     * idioma de requisicao para herdar).
     */
    public function preferredLocale(): ?string
    {
        return $this->locale ?? Locales::fallback();
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
