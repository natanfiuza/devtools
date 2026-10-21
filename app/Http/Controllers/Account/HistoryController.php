<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\AiRequest;
use App\Support\Format;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Historico de uso e consultas da conta.
 *
 * Lista as solicitacoes de ferramentas (AI SQL Builder e proximas) com
 * seus respectivos dialetos, status, tokens/usos consumidos e dados de retorno.
 */
class HistoryController extends Controller
{
    private const PER_PAGE = 20;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $paginator = $user->aiRequests()
            ->with('outputTarget')
            ->latest('created_at')
            ->paginate(self::PER_PAGE);

        $items = array_map(function (AiRequest $req): array {
            $result = $req->result();
            $tokensUsed = ($req->tokens_in ?? 0) + ($req->tokens_out ?? 0);
            if ($tokensUsed === 0 && $req->reserved_tokens > 0) {
                $tokensUsed = $req->reserved_tokens;
            }

            return [
                'id' => $req->id,
                'tool' => $req->outputTarget->label,
                'dialect' => $req->dialect->label(),
                'status' => $req->status->value,
                'quotaUnit' => $req->quota_unit->value,
                'tokensUsed' => $tokensUsed,
                'tokensIn' => $req->tokens_in,
                'tokensOut' => $req->tokens_out,
                'cacheHit' => (bool) $req->cache_hit,
                'createdAt' => Format::dateTime($req->created_at) ?? '',
                'sql' => is_array($result) && isset($result['sql']) && is_string($result['sql']) ? $result['sql'] : null,
                'summary' => is_array($result) && isset($result['summary']) && is_string($result['summary']) ? $result['summary'] : null,
                'errorCode' => $req->error_code?->value,
            ];
        }, $paginator->items());

        return Inertia::render('dashboard/History', [
            'history' => [
                'data' => $items,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'total' => $paginator->total(),
        ]);
    }
}
