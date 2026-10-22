<?php

namespace App\Http\Controllers\Account;

use App\Actions\Ai\ClearHistory;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateHistoryPreferenceRequest;
use App\Models\AiRequest;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Historico de consultas da conta.
 *
 * O historico e opt-in e exclusivo dos planos pagos (ADR-051): no Free a tela
 * responde com `locked` e o CTA de upgrade, sem consultar nenhuma linha. Nos
 * planos pagos lista so o que foi gravado com `history_visible`, que por sua
 * vez depende do toggle. Desligar o toggle nao apaga nada - as linhas antigas
 * continuam visiveis ate o "apagar historico".
 */
class HistoryController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $locked = $user->effectivePlan()->isFree();

        $props = [
            'enabled' => (bool) $user->history_enabled,
            'locked' => $locked,
            'retentionDays' => max(1, (int) config('ai.history.retention_days', 90)),
            'upgradeUrl' => route('pricing'),
            'history' => [
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 0,
            ],
            'total' => 0,
        ];

        if ($locked) {
            return Inertia::render('dashboard/History', $props);
        }

        $paginator = $user->aiRequests()
            ->where('history_visible', true)
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
                'summary' => $this->summary($result),
                'errorCode' => $req->error_code?->value,
            ];
        }, $paginator->items());

        return Inertia::render('dashboard/History', [
            ...$props,
            'history' => [
                'data' => $items,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'total' => $paginator->total(),
        ]);
    }

    /**
     * Liga/desliga a gravacao de novas consultas.
     *
     * Nao toca nas linhas existentes: quem quiser apagar o passado usa o
     * `destroy`. O `paired` vem do formulario como booleano.
     */
    public function updatePreference(UpdateHistoryPreferenceRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'history_enabled' => (bool) $request->validated('history_enabled'),
        ])->save();

        return redirect()
            ->route('dashboard.history')
            ->with('status', 'history-preference-saved');
    }

    /**
     * Apaga o historico terminal da conta. O ledger de cota fica intacto.
     */
    public function destroy(Request $request, ClearHistory $clearHistory): RedirectResponse
    {
        $deleted = $clearHistory($request->user());

        return redirect()
            ->route('dashboard.history')
            ->with('status', 'history-cleared')
            ->with('deleted', $deleted);
    }

    /**
     * Resumo curto para a lista, derivado do contrato da IA.
     *
     * O contrato nao tem um campo `summary`: quando o modelo pede
     * esclarecimento, o texto util e a pergunta; senao, vale a primeira
     * premissa, as tabelas envolvidas ou a primeira linha do SQL. A ordem e a
     * mesma da utilidade para quem le a lista de relance.
     *
     * @param  array<string, mixed>|null  $result
     */
    private function summary(?array $result): ?string
    {
        if ($result === null) {
            return null;
        }

        $clarification = $result['clarification'] ?? null;

        if (($result['needs_clarification'] ?? false) === true
            && is_string($clarification)
            && trim($clarification) !== '') {
            return Str::limit(trim($clarification), 120);
        }

        $assumptions = $result['assumptions'] ?? null;

        if (is_array($assumptions)) {
            foreach ($assumptions as $assumption) {
                if (is_string($assumption) && trim($assumption) !== '') {
                    return Str::limit(trim($assumption), 120);
                }
            }
        }

        $tables = $result['tables_used'] ?? null;

        if (is_array($tables)) {
            $tables = array_values(array_filter(
                $tables,
                fn (mixed $table): bool => is_string($table) && trim($table) !== '',
            ));

            if ($tables !== []) {
                return Str::limit(implode(', ', $tables), 120);
            }
        }

        $sql = $result['sql'] ?? null;

        if (is_string($sql)) {
            foreach (preg_split('/\R/', $sql) ?: [] as $line) {
                if (trim($line) !== '') {
                    return Str::limit(trim($line), 120);
                }
            }
        }

        return null;
    }
}
