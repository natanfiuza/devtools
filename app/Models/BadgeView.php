<?php

namespace App\Models;

use App\Enums\BadgeViewSubject;
use Database\Factories\BadgeViewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Visualizacao bruta de uma imagem com dono (F7, ADR-086).
 *
 * Uma linha por acesso, inclusive 304. Nao guarda IP nem UA em claro: os
 * hashes sao HMAC-SHA256 com `APP_KEY`; o UA cru e opcional e truncado.
 *
 * @property int $id
 * @property BadgeViewSubject $subject_type
 * @property string $subject_id
 * @property int $user_id
 * @property string|null $definition
 * @property string $ip_hash
 * @property string $user_agent_hash
 * @property string|null $user_agent
 * @property string|null $referer_domain
 * @property string|null $country
 * @property string $format
 * @property string|null $style
 * @property bool|null $cache_hit
 * @property Carbon $viewed_at
 * @property-read User $user
 */
#[Fillable([
    'subject_type', 'subject_id', 'user_id', 'definition', 'ip_hash',
    'user_agent_hash', 'user_agent', 'referer_domain', 'country', 'format',
    'style', 'cache_hit', 'viewed_at',
])]
class BadgeView extends Model
{
    /** @use HasFactory<BadgeViewFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_type' => BadgeViewSubject::class,
            'cache_hit' => 'boolean',
            'viewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
