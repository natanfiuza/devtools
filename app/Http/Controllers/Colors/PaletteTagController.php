<?php

namespace App\Http\Controllers\Colors;

use App\Http\Controllers\Controller;
use App\Http\Requests\Colors\ReorderColorTagsRequest;
use App\Http\Requests\Colors\StoreColorTagRequest;
use App\Http\Requests\Colors\UpdateColorTagRequest;
use App\Models\ColorTag;
use App\Models\User;
use App\Support\Colors\PaletteTags;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Tags da conta (F4, ADR-103).
 *
 * Uma tag pertence ao usuario, nao a paleta: renomear reflete em todas as
 * vinculadas e a ordem e unica na conta. Tag de outra conta responde 404.
 */
class PaletteTagController extends Controller
{
    public function store(StoreColorTagRequest $request, PaletteTags $tags): RedirectResponse
    {
        $tags->create($this->user($request), (string) $request->validated('name'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.toasts.tag_created'),
        ]);

        return back();
    }

    public function update(
        UpdateColorTagRequest $request,
        ColorTag $colorTag,
        PaletteTags $tags,
    ): RedirectResponse {
        $this->ensureOwner($request, $colorTag);

        $tags->rename($colorTag, (string) $request->validated('name'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.toasts.tag_updated'),
        ]);

        return back();
    }

    public function reorder(ReorderColorTagsRequest $request, PaletteTags $tags): RedirectResponse
    {
        /** @var list<string> $ids */
        $ids = $request->validated('ids');

        $tags->reorder($this->user($request), $ids);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.toasts.tag_reordered'),
        ]);

        return back();
    }

    public function destroy(Request $request, ColorTag $colorTag, PaletteTags $tags): RedirectResponse
    {
        $this->ensureOwner($request, $colorTag);

        $tags->delete($colorTag);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('colors.toasts.tag_deleted'),
        ]);

        return back();
    }

    private function ensureOwner(Request $request, ColorTag $tag): void
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && (int) $tag->user_id === (int) $user->getKey(),
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
