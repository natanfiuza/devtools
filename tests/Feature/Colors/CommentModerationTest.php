<?php

use App\Actions\Colors\ApplyModerationResult;
use App\Enums\ColorCommentReportReason;
use App\Enums\ColorCommentStatus;
use App\Jobs\ModerateColorComment;
use App\Models\AiCache;
use App\Models\ColorPaletteComment;
use App\Support\Colors\Moderation\ModerationPayload;
use App\Support\Colors\Moderation\ModerationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
| Cadeia de moderacao da F7 (ADR-140).
|
| O job chama o provedor diretamente: sem reserva de cota e sem
| `usage_ledger`. So o UPDATE condicional por revisao aplica um veredito, e
| qualquer falha definitiva termina em `flagged`, nunca em aprovacao.
*/

beforeEach(function () {
    config([
        'ai.base_url' => 'https://api.deepseek.test',
        'ai.model' => 'deepseek-chat-test',
        'ai.api_key' => 'test-key',
        'colors.comments.moderation.model' => 'moderation-test-model',
        'colors.comments.moderation.prompt_slug' => 'colors_comment_moderation',
        'colors.comments.moderation.prompt_version' => 1,
        'colors.comments.moderation.policy_version' => 1,
        'colors.comments.moderation.cache_ttl_days' => null,
    ]);
});

function moderateNow(ColorPaletteComment $comment): void
{
    app()->call([
        new ModerateColorComment($comment->id, $comment->revision),
        'handle',
    ]);
}

function fakeModeration(string $json): void
{
    Http::fake([
        'https://api.deepseek.test/chat/completions' => Http::response([
            'model' => 'moderation-test-model',
            'choices' => [
                ['message' => ['content' => $json]],
            ],
            'usage' => ['prompt_tokens' => 30, 'completion_tokens' => 8],
        ], 200),
    ]);
}

it('aprova um comentario pendente e guarda o texto aprovado', function () {
    $comment = ColorPaletteComment::factory()->create(['body' => 'Adorei a paleta!']);

    fakeModeration('{"decision":"approved","category":null}');

    moderateNow($comment);

    $comment->refresh();

    expect($comment->status)->toBe(ColorCommentStatus::Approved)
        ->and($comment->approved_body)->toBe('Adorei a paleta!')
        ->and($comment->moderated_at)->not->toBeNull()
        ->and($comment->moderation_reason)->toBeNull()
        ->and(DB::table('usage_ledger')->count())->toBe(0);
});

it('usa o modelo da moderacao na chamada sem debitar cota', function () {
    $comment = ColorPaletteComment::factory()->create();

    fakeModeration('{"decision":"approved","category":null}');

    moderateNow($comment);

    Http::assertSent(fn ($request): bool => $request['model'] === 'moderation-test-model');
});

it('rejeita e mantem a ultima versao aprovada quando a edicao e barrada', function () {
    $comment = ColorPaletteComment::factory()->approved()->create([
        'body' => 'texto antigo aprovado',
        'approved_body' => 'texto antigo aprovado',
    ]);

    $comment->forceFill([
        'body' => 'texto novo abusivo',
        'revision' => 2,
        'status' => ColorCommentStatus::Pending,
    ])->save();

    fakeModeration('{"decision":"rejected","category":"harassment"}');

    moderateNow($comment->refresh());

    $comment->refresh();

    expect($comment->status)->toBe(ColorCommentStatus::Rejected)
        ->and($comment->approved_body)->toBe('texto antigo aprovado')
        ->and($comment->displayBody())->toBe('texto antigo aprovado')
        ->and($comment->moderation_reason)->toBe('harassment')
        ->and($comment->isVisibleToPublic())->toBeTrue();
});

it('rejeita comentario sem versao aprovada e o esconde', function () {
    $comment = ColorPaletteComment::factory()->create();

    fakeModeration('{"decision":"rejected","category":"spam"}');

    moderateNow($comment);

    $comment->refresh();

    expect($comment->status)->toBe(ColorCommentStatus::Rejected)
        ->and($comment->approved_body)->toBeNull()
        ->and($comment->displayBody())->toBeNull()
        ->and($comment->isVisibleToPublic())->toBeFalse();
});

it('nao aprova revisao nova com resultado de revisao antiga', function () {
    $comment = ColorPaletteComment::factory()->create(['revision' => 2]);

    $applied = app(ApplyModerationResult::class)(
        $comment->id,
        1,
        new ModerationResult(ColorCommentStatus::Approved),
    );

    expect($applied)->toBeFalse()
        ->and($comment->refresh()->status)->toBe(ColorCommentStatus::Pending);
});

it('nao chama o provedor de novo quando o mesmo texto esta no cache', function () {
    $first = ColorPaletteComment::factory()->create(['body' => 'Comentario repetido']);
    $second = ColorPaletteComment::factory()->create(['body' => 'Comentario repetido']);

    fakeModeration('{"decision":"approved","category":null}');

    moderateNow($first);
    moderateNow($second);

    Http::assertSentCount(1);

    expect($first->refresh()->status)->toBe(ColorCommentStatus::Approved)
        ->and($second->refresh()->status)->toBe(ColorCommentStatus::Approved)
        ->and(AiCache::query()->where('purpose', ModerationPayload::PURPOSE)->count())->toBe(1);
});

it('marca como flagged quando o contrato do modelo e invalido', function () {
    $comment = ColorPaletteComment::factory()->create();

    fakeModeration('{"decision":"flagged","category":null}');

    moderateNow($comment);

    $comment->refresh();

    expect($comment->status)->toBe(ColorCommentStatus::Flagged)
        ->and($comment->moderation_reason)->toBe('invalid_contract')
        ->and($comment->approved_body)->toBeNull();
});

it('marca como flagged quando o provedor nao esta configurado', function () {
    config(['ai.base_url' => null]);

    $comment = ColorPaletteComment::factory()->create();

    moderateNow($comment);

    $comment->refresh();

    expect($comment->status)->toBe(ColorCommentStatus::Flagged)
        ->and($comment->moderation_reason)->toBe('provider_unconfigured');
});

it('marca como flagged quando o provedor rejeita de forma definitiva', function () {
    $comment = ColorPaletteComment::factory()->create();

    Http::fake([
        '*' => Http::response('conteudo interno do provedor', 422),
    ]);

    moderateNow($comment);

    $comment->refresh();

    expect($comment->status)->toBe(ColorCommentStatus::Flagged)
        ->and($comment->moderation_reason)->toBe('provider_unavailable');
});

it('marca como flagged no failed quando a fila esgota as tentativas', function () {
    $comment = ColorPaletteComment::factory()->create();

    (new ModerateColorComment($comment->id, $comment->revision))
        ->failed(new RuntimeException('fila esgotada'));

    $comment->refresh();

    expect($comment->status)->toBe(ColorCommentStatus::Flagged)
        ->and($comment->moderation_reason)->toBe('job_failed');
});

it('nao modera comentario excluido nem revisao que saiu de pending', function () {
    $deleted = ColorPaletteComment::factory()->deleted()->create();
    $already = ColorPaletteComment::factory()->approved()->create();

    Http::fake();

    moderateNow($deleted);
    moderateNow($already);

    Http::assertNothingSent();

    expect($deleted->refresh()->status)->toBe(ColorCommentStatus::Pending)
        ->and($already->refresh()->status)->toBe(ColorCommentStatus::Approved);
});

it('normaliza a categoria do contrato para os motivos conhecidos', function () {
    $comment = ColorPaletteComment::factory()->create();

    fakeModeration('{"decision":"rejected","category":"sexual"}');

    moderateNow($comment);

    $comment->refresh();

    expect($comment->status)->toBe(ColorCommentStatus::Rejected)
        ->and($comment->moderation_reason)->toBe(ColorCommentReportReason::Sexual->value);
});
