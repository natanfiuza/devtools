<?php

namespace App\Models;

use App\Enums\AdminActionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Uma linha da auditoria administrativa (ADR-025).
 *
 * `meta` e contexto curto da acao - `['de' => 'user', 'para' => 'admin']`.
 * Nunca conteudo de schema, de prompt ou dado sensivel.
 *
 * @property int $id
 * @property int|null $actor_id
 * @property AdminActionType $action
 * @property string|null $target_type
 * @property int|null $target_id
 * @property array<string, mixed>|null $meta
 * @property string|null $ip
 * @property Carbon|null $created_at
 * @property-read User|null $actor
 */
#[Fillable(['actor_id', 'action', 'target_type', 'target_id', 'meta', 'ip'])]
class AdminAction extends Model
{
    /**
     * Auditoria nao se edita: so a data de criacao e gerenciada pelo Eloquent.
     */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AdminActionType::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
