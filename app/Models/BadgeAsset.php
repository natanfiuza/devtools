<?php

namespace App\Models;

use Database\Factories\BadgeAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Badge pessoal do BadgeForge (F4, ADR-081).
 *
 * `type` e `static` (snapshot do formulario renderizado no servidor) ou
 * `provider` (metrica externa, possivelmente de item privado). O PAT fica
 * cifrado no cast; a chave de cache usa apenas `credential_fingerprint`
 * (HMAC), nunca o token. Os paths sao relativos a base de
 * `badgeforge.personal_badges.path`.
 *
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string $type
 * @property bool $private
 * @property string|null $provider_slug
 * @property string|null $definition_slug
 * @property array<string, string>|null $params
 * @property array<string, mixed>|null $form
 * @property string|null $credential
 * @property string|null $credential_username
 * @property string|null $credential_fingerprint
 * @property Carbon|null $credential_set_at
 * @property string|null $svg_path
 * @property string|null $png_path
 * @property string|null $etag
 * @property int|null $bytes
 * @property Carbon|null $last_generated_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable([
    'id', 'user_id', 'name', 'type', 'private', 'provider_slug',
    'definition_slug', 'params', 'form', 'credential', 'credential_username',
    'credential_fingerprint', 'credential_set_at', 'svg_path', 'png_path',
    'etag', 'bytes', 'last_generated_at', 'expires_at',
])]
#[Hidden(['credential', 'credential_fingerprint'])]
class BadgeAsset extends Model
{
    /** @use HasFactory<BadgeAssetFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'private' => 'boolean',
            'params' => 'array',
            'form' => 'array',
            'credential' => 'encrypted',
            'credential_set_at' => 'datetime',
            'bytes' => 'integer',
            'last_generated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isProvider(): bool
    {
        return $this->type === 'provider';
    }

    public function credentialSet(): bool
    {
        return $this->credential_set_at !== null;
    }

    /**
     * URL publica do SVG; `null` quando a conta ainda nao escolheu username.
     */
    public function publicSvgUrl(): ?string
    {
        return $this->publicUrl('svg');
    }

    /**
     * URL publica do PNG; `null` quando a conta ainda nao escolheu username.
     */
    public function publicPngUrl(): ?string
    {
        return $this->publicUrl('png');
    }

    public function publicUrl(string $extension): ?string
    {
        $username = $this->user->username;

        if (! is_string($username) || $username === '') {
            return null;
        }

        $base = rtrim((string) config('badgeforge.personal_endpoint', '/b'), '/');

        return url($base.'/'.$username.'/'.$this->getKey().'.'.$extension);
    }
}
