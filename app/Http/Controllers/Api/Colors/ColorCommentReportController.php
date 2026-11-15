<?php

namespace App\Http\Controllers\Api\Colors;

use App\Actions\Colors\ReportColorComment;
use App\Enums\ColorCommentReportReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Colors\StoreColorCommentReportRequest;
use App\Models\ColorPalette;
use App\Models\ColorPaletteComment;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Denúncia de comentário (F7, ADR-141).
 *
 * Só conta logada denuncia, e só comentário publicado em paleta pública.
 * Comentário de outra conta continua respondendo 404; comentário próprio é
 * recusado pela action com 422, porque o denunciante já sabe de quem é.
 */
class ColorCommentReportController extends Controller
{
    public function store(
        StoreColorCommentReportRequest $request,
        ColorPaletteComment $colorPaletteComment,
        ReportColorComment $report,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $this->authorizePalette($colorPaletteComment->palette);
        abort_unless(
            $colorPaletteComment->isVisibleToPublic() && $colorPaletteComment->deleted_at === null,
            404,
        );

        /** @var array{reason: string} $data */
        $data = $request->validated();

        $created = $report(
            $user,
            $colorPaletteComment,
            ColorCommentReportReason::from($data['reason']),
        );

        return response()->json([
            'report' => [
                'id' => $created->id,
                'reason' => $created->reason->value,
                'status' => $created->status->value,
                'reported_at' => $created->reported_at->toIso8601String(),
            ],
        ], 201);
    }

    private function authorizePalette(ColorPalette $palette): void
    {
        abort_unless(
            $palette->isPublic() && ! $palette->isArchived() && $palette->slug !== null,
            404,
        );
    }
}
