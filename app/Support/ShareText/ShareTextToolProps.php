<?php

namespace App\Support\ShareText;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Props para a ferramenta Share Text na pagina generica (ADR-043, ADR-058).
 */
final class ShareTextToolProps
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?User $user, ?Request $request = null): array
    {
        $initialDocument = null;

        if ($request !== null && $request->route('id') !== null) {
            $id = (string) $request->route('id');
            $sharedText = ShareTextStorage::find($id);

            abort_if($sharedText === null, 404);

            $content = $sharedText->getContent();
            abort_if($content === null, 404);

            $initialDocument = [
                'key' => $sharedText->id,
                'data' => $content,
                'extension' => $sharedText->extension ?? ShareTextStorage::extractExtension($id),
                'created_at' => $sharedText->created_at?->toIso8601String(),
                'size_bytes' => $sharedText->size_bytes,
                'is_owner' => $user !== null && $sharedText->user_id === $user->id,
            ];
        }

        $plan = $user?->effectivePlan();
        $isPaidPlan = $plan !== null && ! $plan->isFree();

        return [
            'isAuthenticated' => $user !== null,
            'isPaidPlan' => $isPaidPlan,
            'maxUploadBytes' => ShareTextStorage::maxBytesFor($user),
            'retentionHours' => $user === null ? (int) config('tools.limits.share_text.anonymous_retention_hours', 72) : null,
            'initialDocument' => $initialDocument,
        ];
    }
}
