<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminActionType;
use App\Http\Controllers\Admin\Concerns\RecordsAdminActions;
use App\Http\Controllers\Controller;
use App\Models\AiCache;
use App\Support\Ai\AiCacheRepository;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cache de respostas da IA (F7).
 *
 * A tela mostra **so metadados**: hash truncado, modelo, versao do prompt,
 * tokens, acertos e datas. Prompt, schema, payload canonico e resposta ficam na
 * linha e nunca chegam ao navegador - nem no HTML, nem nas props (regra do
 * CLAUDE.md).
 *
 * A unica escrita e a poda: apagar as linhas vencidas. A expiracao ja e
 * checada na leitura; a poda evita guardar schema antigo indefinidamente.
 */
class AiCacheController extends Controller
{
    use RecordsAdminActions;

    private const PER_PAGE = 30;

    public function index(Request $request): Response
    {
        $state = $request->string('state')->value();

        $entries = AiCache::query()
            ->when(
                $state === 'expired',
                fn (Builder $query) => $query->where('expires_at', '<=', now()),
            )
            ->when(
                $state === 'valid',
                fn (Builder $query) => $query->where('expires_at', '>', now()),
            )
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/AiCache', [
            'entries' => $entries->through(fn (AiCache $entry): array => [
                'id' => $entry->id,
                // O hash identifica a entrada; 12 caracteres bastam para
                // conferir e nao carregam payload nenhum.
                'hash' => substr($entry->prompt_hash, 0, 12),
                'model' => $entry->model,
                'promptVersion' => $entry->prompt_version,
                'tokensIn' => $entry->tokens_in,
                'tokensOut' => $entry->tokens_out,
                'hits' => $entry->hits,
                'createdAt' => Format::dateTime($entry->created_at),
                'expiresAt' => Format::dateTime($entry->expires_at),
                'expired' => $entry->isExpired(),
            ]),
            'filters' => ['state' => $state],
            'expired' => AiCache::query()->where('expires_at', '<=', now())->count(),
            'total' => $entries->total(),
        ]);
    }

    public function destroyExpired(Request $request, AiCacheRepository $cache): RedirectResponse
    {
        $deleted = $cache->deleteExpired();

        if ($deleted === 0) {
            return $this->back('info', __('admin.toasts.ai_cache_empty'));
        }

        $this->record($request, AdminActionType::AiCachePruned, null, [
            'removidas' => $deleted,
        ]);

        return $this->back('success', __('admin.toasts.ai_cache_pruned', ['count' => $deleted]));
    }

    private function back(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }
}
