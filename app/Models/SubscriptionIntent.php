<?php

namespace App\Models;

use App\Enums\SubscriptionIntentPaymentMethod;
use App\Enums\SubscriptionIntentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pedido de intencao de assinatura Pro preenchido pelo usuario (ADR-033).
 *
 * Enquanto o checkout de autoatendimento nao entra (F6), a intencao substitui o
 * link `mailto:` na pagina de planos: coleta o plano escolhido, o `@username`
 * reservado, telefone/WhatsApp, CPF/CNPJ e forma de pagamento preferida, avisa
 * o admin por e-mail e fica na fila `/admin/intents`.
 *
 * Ao excluir/anonimizar a conta (ADR-028), as intencoes sao apagadas porque nao
 * sao documento fiscal (ao contrario de `payments`) e carregam telefone e
 * documento pessoal.
 *
 * @property int $id
 * @property int $user_id
 * @property int $plan_id
 * @property string $username
 * @property string $phone
 * @property string|null $document
 * @property SubscriptionIntentPaymentMethod $payment_method
 * @property string|null $notes
 * @property SubscriptionIntentStatus $status
 * @property int|null $handled_by
 * @property Carbon|null $handled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Plan $plan
 * @property-read User|null $handler
 */
#[Fillable([
    'user_id',
    'plan_id',
    'username',
    'phone',
    'document',
    'payment_method',
    'notes',
    'status',
    'handled_by',
    'handled_at',
])]
class SubscriptionIntent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_method' => SubscriptionIntentPaymentMethod::class,
            'status' => SubscriptionIntentStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
