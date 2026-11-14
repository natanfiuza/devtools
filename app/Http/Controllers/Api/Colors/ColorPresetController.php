<?php

namespace App\Http\Controllers\Api\Colors;

use App\Actions\Colors\CreateColorPreset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Colors\StoreColorPresetRequest;
use App\Http\Requests\Colors\UpdateColorPresetRequest;
use App\Models\ColorPreset;
use App\Models\User;
use App\Support\Colors\ColorPresetQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Presets do gerador da conta (F5, ADR-093).
 *
 * Uma configuracao reutilizavel nao pode virar uma segunda paleta: aqui nao
 * existe visibilidade, cota de paletas, URL publica nem contador de uso. O
 * servidor so guarda a entrada (nome + base); aplicar e tarefa do navegador.
 * Recurso de outra conta responde 404, nunca 403.
 */
class ColorPresetController extends Controller
{
    public function index(Request $request, ColorPresetQuota $quota): JsonResponse
    {
        $user = $this->user($request);

        $presets = ColorPreset::query()
            ->forUser((int) $user->getKey())
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $presets
                ->map(fn (ColorPreset $preset): array => $this->payload($preset))
                ->values()
                ->all(),
            'limits' => $quota->summary($user),
            // O teto do nome vem da config: o cliente nao fixa limite.
            'form' => [
                'name_max_length' => (int) config('colors.presets.name_max_length', 60),
            ],
        ]);
    }

    public function store(
        StoreColorPresetRequest $request,
        CreateColorPreset $create,
    ): JsonResponse {
        $preset = $create(
            $this->user($request),
            (string) $request->validated('name'),
            (string) $request->validated('base'),
        );

        return response()->json(['preset' => $this->payload($preset)], 201);
    }

    public function update(
        UpdateColorPresetRequest $request,
        ColorPreset $colorPreset,
    ): JsonResponse {
        $this->ensureOwner($request, $colorPreset);

        $colorPreset->forceFill([
            'name' => (string) $request->validated('name'),
        ])->save();

        return response()->json(['preset' => $this->payload($colorPreset->refresh())]);
    }

    public function destroy(Request $request, ColorPreset $colorPreset): JsonResponse
    {
        $this->ensureOwner($request, $colorPreset);

        $colorPreset->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array{id: string, name: string, base: string, created_at: string|null, updated_at: string|null}
     */
    private function payload(ColorPreset $preset): array
    {
        return [
            'id' => $preset->id,
            'name' => $preset->name,
            'base' => $preset->base,
            'created_at' => $preset->created_at?->toIso8601String(),
            'updated_at' => $preset->updated_at?->toIso8601String(),
        ];
    }

    private function ensureOwner(Request $request, ColorPreset $preset): void
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && (int) $preset->user_id === (int) $user->getKey(),
            404,
        );
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
