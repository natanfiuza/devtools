<?php

namespace App\Models;

use Database\Factories\SharedTextFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Registro de texto compartilhado na ferramenta Share Text (ADR-058).
 *
 * @property string $id
 * @property int|null $user_id
 * @property string $file_path
 * @property int $size_bytes
 * @property string|null $extension
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable([
    'id',
    'user_id',
    'file_path',
    'size_bytes',
    'extension',
    'expires_at',
])]
class SharedText extends Model
{
    /** @use HasFactory<SharedTextFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
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

    /**
     * Informa se o texto expirou.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Le o conteudo do arquivo fisico no disco.
     */
    public function getContent(): ?string
    {
        if (File::exists($this->file_path)) {
            return File::get($this->file_path);
        }

        return null;
    }

    /**
     * Remove o arquivo fisico do disco.
     */
    public function deletePhysicalFile(): void
    {
        if (File::exists($this->file_path)) {
            File::delete($this->file_path);
        }
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }
}
