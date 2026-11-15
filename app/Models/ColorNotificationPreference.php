<?php

namespace App\Models;

use Database\Factories\ColorNotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Preferencias de e-mail das notificacoes Colors (F7, ADR-142).
 *
 * O canal `database` e sempre ligado; estas flags decidem somente o e-mail.
 * Sem linha, valem os defaults: comentario e resposta ligados, curtida e novo
 * seguidor desligados. A linha e criada na primeira alteracao, nao no cadastro.
 *
 * @property int $user_id
 * @property bool $email_on_like
 * @property bool $email_on_follow
 * @property bool $email_on_comment
 * @property bool $email_on_reply
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'email_on_like',
    'email_on_follow',
    'email_on_comment',
    'email_on_reply',
])]
class ColorNotificationPreference extends Model
{
    /** @use HasFactory<ColorNotificationPreferenceFactory> */
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'color_notification_preferences';

    /**
     * @var string
     */
    protected $primaryKey = 'user_id';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $keyType = 'int';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_on_like' => 'boolean',
            'email_on_follow' => 'boolean',
            'email_on_comment' => 'boolean',
            'email_on_reply' => 'boolean',
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        return [
            'email_on_like' => false,
            'email_on_follow' => false,
            'email_on_comment' => true,
            'email_on_reply' => true,
        ];
    }

    /**
     * Preferencia da conta, ja com os defaults quando ainda nao existe linha.
     */
    public static function for(User $user): self
    {
        return self::query()->firstOrNew(
            ['user_id' => $user->getKey()],
            self::defaults(),
        );
    }

    /**
     * O e-mail vale para o tipo de aviso? `like`, `follow`, `comment` e `reply`
     * sao os quatro toggles expostos na pagina de notificacoes.
     */
    public function allowsEmail(string $kind): bool
    {
        return match ($kind) {
            'like' => $this->email_on_like,
            'follow' => $this->email_on_follow,
            'comment' => $this->email_on_comment,
            'reply' => $this->email_on_reply,
            default => false,
        };
    }
}
