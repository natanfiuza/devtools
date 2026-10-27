<?php

namespace App\Http\Controllers;

use App\Models\SharedText;
use App\Models\User;
use App\Support\Locales;
use App\Support\ShareText\ShareTextStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Endpoints da ferramenta Share Text e compatibilidade Hastebin (ADR-058).
 */
class ShareTextController extends Controller
{
    /**
     * Endpoint padrao Hastebin para salvar um novo texto (POST /documents).
     */
    public function store(Request $request): JsonResponse
    {
        $content = '';

        if ($request->isJson() && $request->has('data')) {
            $content = (string) $request->input('data');
        } elseif ($request->filled('data')) {
            $content = (string) $request->input('data');
        } else {
            $content = (string) $request->getContent();
        }

        $extension = $request->input('extension');
        if (! is_string($extension) || $extension === '') {
            $extension = null;
        }

        /** @var User|null $user */
        $user = $request->user() ?? auth('sanctum')->user();

        $sharedText = ShareTextStorage::store($content, $user, $extension);

        return response()->json([
            'key' => $sharedText->id,
        ], 201);
    }

    /**
     * Endpoint padrao Hastebin para buscar o documento (GET /documents/{id}).
     */
    public function show(string $id): JsonResponse
    {
        $sharedText = ShareTextStorage::find($id);

        if ($sharedText === null) {
            return response()->json([
                'message' => 'Document not found.',
            ], 404);
        }

        $content = $sharedText->getContent();

        if ($content === null) {
            return response()->json([
                'message' => 'Document not found.',
            ], 404);
        }

        return response()->json([
            'key' => $sharedText->id,
            'data' => $content,
        ]);
    }

    /**
     * Visualizacao de texto puro cru (GET /raw/{id}).
     */
    public function raw(string $id): Response
    {
        $sharedText = ShareTextStorage::find($id);

        abort_if($sharedText === null, 404);

        $content = $sharedText->getContent();
        abort_if($content === null, 404);

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Redireciona o link curto /share/{id} para a rota web no idioma preferido do visitante.
     */
    public function redirectShort(Request $request, string $id): RedirectResponse
    {
        $sharedText = ShareTextStorage::find($id);

        abort_if($sharedText === null, 404);

        $slug = Locales::slug(Locales::preferred($request));

        return redirect()->to("/{$slug}/tools/share-text/{$id}");
    }

    /**
     * Historico de textos salvos pelo usuario autenticado.
     */
    public function history(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $items = SharedText::query()
            ->forUser($user->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'size_bytes', 'extension', 'created_at']);

        return response()->json([
            'data' => $items,
        ]);
    }

    /**
     * Exclui permanentemente um texto do usuario autenticado.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var SharedText $sharedText */
        $sharedText = SharedText::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        ShareTextStorage::delete($sharedText);

        return response()->json([
            'message' => __('tools.share-text.messages.deleted'),
        ]);
    }
}
