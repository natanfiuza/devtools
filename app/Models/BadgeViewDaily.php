<?php

namespace App\Models;

use App\Enums\BadgeViewSubject;
use Database\Factories\BadgeViewDailyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Agregado diario de visualizacoes (F7, ADR-086).
 *
 * Dimensoes opcionais vazias sao `''`, nunca NULL, para o indice unico do
 * MySQL funcionar. `unique_visitors` e distinto por dia.
 *
 * @property int $id
 * @property BadgeViewSubject $subject_type
 * @property string $subject_id
 * @property int $user_id
 * @property Carbon $day
 * @property string $definition
 * @property string $country
 * @property string $referer_domain
 * @property string $format
 * @property int $views
 * @property int $unique_visitors
 * @property int $cache_hits
 * @property-read User $user
 */
#[Fillable([
    'subject_type', 'subject_id', 'user_id', 'day', 'definition', 'country',
    'referer_domain', 'format', 'views', 'unique_visitors', 'cache_hits',
])]
class BadgeViewDaily extends Model
{
    /** @use HasFactory<BadgeViewDailyFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_type' => BadgeViewSubject::class,
            'day' => 'date',
            'views' => 'integer',
            'unique_visitors' => 'integer',
            'cache_hits' => 'integer',
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
