<?php

namespace App\Models;

use Database\Factories\Base64ImageConversionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Historico de conversao da ferramenta Base64 Image (ADR-055).
 *
 * @property string $id
 * @property int|null $user_id
 * @property string $filename
 * @property string $mime_type
 * @property int $file_size_bytes
 * @property int $base64_size_bytes
 * @property bool $is_paid_plan
 * @property string|null $disk_path_original
 * @property string|null $disk_path_converted
 * @property Carbon|null $file_deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable([
    'id',
    'user_id',
    'filename',
    'mime_type',
    'file_size_bytes',
    'base64_size_bytes',
    'is_paid_plan',
    'disk_path_original',
    'disk_path_converted',
    'file_deleted_at',
])]
class Base64ImageConversion extends Model
{
    /** @use HasFactory<Base64ImageConversionFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'base64_size_bytes' => 'integer',
            'is_paid_plan' => 'boolean',
            'file_deleted_at' => 'datetime',
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
     * Verifica se os arquivos fisicos desta conversao ja foram removidos.
     */
    public function isExpired(): bool
    {
        return $this->file_deleted_at !== null;
    }

    /**
     * Exclui os arquivos fisicos associados no disco e marca file_deleted_at.
     */
    public function deletePhysicalFiles(): void
    {
        if ($this->disk_path_original && File::exists($this->disk_path_original)) {
            File::delete($this->disk_path_original);
        }

        if ($this->disk_path_converted && File::exists($this->disk_path_converted)) {
            File::delete($this->disk_path_converted);
        }

        $this->update(['file_deleted_at' => now()]);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }
}
