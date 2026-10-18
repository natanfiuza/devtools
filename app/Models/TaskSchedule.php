<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $command
 * @property array<string, mixed>|null $parameters
 * @property string $expression
 * @property bool $is_active
 * @property CarbonImmutable|null $last_run_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'command', 'parameters', 'expression', 'is_active', 'last_run_at'])]
class TaskSchedule extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }
}
