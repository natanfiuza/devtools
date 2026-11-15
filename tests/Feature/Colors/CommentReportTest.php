<?php

use App\Actions\Colors\ApplyModerationResult;
use App\Enums\AdminActionType;
use App\Enums\ColorCommentReportReason;
use App\Enums\ColorCommentReportStatus;
use App\Enums\ColorCommentStatus;
use App\Models\AdminAction;
use App\Models\ColorCommentReport;
use App\Models\ColorPalette;
use App\Models\ColorPaletteComment;
use App\Models\User;
use App\Support\Colors\Moderation\ModerationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Denuncias de comentario (F7, ADR-141).
|
| Uma linha por par comentario/denunciante: pendente e resolvida nao duplicam;
| dispensada reabre na mesma linha. Excluir ou rejeitar o comentario encerra as
| pendentes. O admin decide resolver ou dispensar, e a decisao fica auditada.
*/

beforeEach(function () {
    config(['colors.comments.enabled' => true]);
    Queue::fake();
    Cache::flush();
});

function reportPalette(string $slug = 'publica'): ColorPalette
{
    return ColorPalette::factory()->public()->create(['slug' => $slug]);
}

function reportableComment(ColorPalette $palette, array $attributes = []): ColorPaletteComment
{
    return ColorPaletteComment::factory()
        ->for($palette, 'palette')
        ->approved()
        ->create($attributes);
}

function reportRoute(ColorPaletteComment $comment): string
{
    return route('api.tools.colors.comments.report.store', $comment);
}

it('exige conta para denunciar', function () {
    $comment = reportableComment(reportPalette());

    $this->postJson(reportRoute($comment), ['reason' => 'spam'])
        ->assertUnauthorized();

    expect(ColorCommentReport::query()->count())->toBe(0);
});

it('registra a denuncia pendente do comentario publicado', function () {
    $reporter = User::factory()->create();
    $comment = reportableComment(reportPalette());

    $this->actingAs($reporter)
        ->postJson(reportRoute($comment), ['reason' => 'harassment'])
        ->assertCreated()
        ->assertJsonPath('report.reason', 'harassment')
        ->assertJsonPath('report.status', 'pending');

    $report = ColorCommentReport::query()->sole();

    expect($report->comment_id)->toBe($comment->id)
        ->and($report->user_id)->toBe($reporter->id)
        ->and($report->reason)->toBe(ColorCommentReportReason::Harassment)
        ->and($report->reported_at)->not->toBeNull()
        ->and($report->resolved_at)->toBeNull();
});

it('recusa denunciar o proprio comentario', function () {
    $author = User::factory()->create();
    $palette = reportPalette();
    $comment = reportableComment($palette, ['user_id' => $author->id]);

    $this->actingAs($author)
        ->postJson(reportRoute($comment), ['reason' => 'spam'])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.errors.reason.0', __('colors.validation.report_own'));

    expect(ColorCommentReport::query()->count())->toBe(0);
});

it('nao duplica denuncia pendente nem resolvida do mesmo denunciante', function () {
    $reporter = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $comment = reportableComment(reportPalette());

    ColorCommentReport::factory()
        ->for($comment, 'comment')
        ->for($reporter, 'reporter')
        ->create(['reason' => ColorCommentReportReason::Spam]);

    $this->actingAs($reporter)
        ->postJson(reportRoute($comment), ['reason' => 'hate'])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.errors.reason.0', __('colors.validation.report_duplicate'));

    ColorCommentReport::query()->update([
        'status' => ColorCommentReportStatus::Resolved,
        'resolved_by' => $admin->id,
        'resolved_at' => now(),
    ]);

    $this->actingAs($reporter)
        ->postJson(reportRoute($comment), ['reason' => 'hate'])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.errors.reason.0', __('colors.validation.report_duplicate'));

    expect(ColorCommentReport::query()->count())->toBe(1);
});

it('reabre a denuncia dispensada na mesma linha', function () {
    $reporter = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $comment = reportableComment(reportPalette());

    $report = ColorCommentReport::factory()
        ->for($comment, 'comment')
        ->for($reporter, 'reporter')
        ->dismissed($admin)
        ->create([
            'reason' => ColorCommentReportReason::Spam,
            'reported_at' => now()->subDay(),
        ]);

    $this->actingAs($reporter)
        ->postJson(reportRoute($comment), ['reason' => 'sexual'])
        ->assertCreated()
        ->assertJsonPath('report.id', $report->id)
        ->assertJsonPath('report.status', 'pending');

    $report->refresh();

    expect(ColorCommentReport::query()->count())->toBe(1)
        ->and($report->status)->toBe(ColorCommentReportStatus::Pending)
        ->and($report->reason)->toBe(ColorCommentReportReason::Sexual)
        ->and($report->resolved_at)->toBeNull()
        ->and($report->resolved_by)->toBeNull()
        ->and($report->reported_at->isToday())->toBeTrue();
});

it('recusa comentario oculto, excluido, de paleta privada ou inexistente', function () {
    $reporter = User::factory()->create();
    $palette = reportPalette();

    $rejected = ColorPaletteComment::factory()
        ->for($palette, 'palette')
        ->rejected()
        ->create();
    $deleted = reportableComment($palette);
    $deleted->delete();
    $private = reportableComment(ColorPalette::factory()->create(['slug' => 'privada']));

    foreach ([$rejected, $deleted, $private] as $comment) {
        $this->actingAs($reporter)
            ->postJson(reportRoute($comment), ['reason' => 'spam'])
            ->assertNotFound();
    }

    $this->actingAs($reporter)
        ->postJson(route('api.tools.colors.comments.report.store', (string) Str::ulid()), [
            'reason' => 'spam',
        ])
        ->assertNotFound();

    expect(ColorCommentReport::query()->count())->toBe(0);
});

it('valida o motivo da denuncia', function () {
    $reporter = User::factory()->create();
    $comment = reportableComment(reportPalette());

    $this->actingAs($reporter)
        ->postJson(reportRoute($comment), ['reason' => 'motivo-inventado'])
        ->assertUnprocessable()
        ->assertJsonPath(
            'error.details.errors.reason.0',
            __('colors.validation.report_reason_invalid'),
        );

    expect(ColorCommentReport::query()->count())->toBe(0);
});

it('encerra as denuncias pendentes quando o autor exclui o comentario', function () {
    $author = User::factory()->create();
    $reporter = User::factory()->create();
    $comment = reportableComment(reportPalette(), ['user_id' => $author->id]);

    $report = ColorCommentReport::factory()
        ->for($comment, 'comment')
        ->for($reporter, 'reporter')
        ->create();

    $this->actingAs($author)
        ->deleteJson(route('api.tools.colors.comments.destroy', $comment))
        ->assertOk();

    $report->refresh();

    expect($report->status)->toBe(ColorCommentReportStatus::Resolved)
        ->and($report->resolved_at)->not->toBeNull()
        ->and($report->resolved_by)->toBeNull();
});

it('encerra as denuncias pendentes quando a moderacao rejeita', function () {
    $reporter = User::factory()->create();
    $comment = ColorPaletteComment::factory()->create();

    $report = ColorCommentReport::factory()
        ->for($comment, 'comment')
        ->for($reporter, 'reporter')
        ->create();

    app(ApplyModerationResult::class)(
        $comment->id,
        $comment->revision,
        new ModerationResult(ColorCommentStatus::Rejected, ColorCommentReportReason::Hate),
    );

    expect($report->refresh()->status)->toBe(ColorCommentReportStatus::Resolved)
        ->and($comment->refresh()->status)->toBe(ColorCommentStatus::Rejected);
});

it('fecha as denuncias quando o interruptor esta desligado', function () {
    config(['colors.comments.enabled' => false]);

    $reporter = User::factory()->create();
    $comment = reportableComment(reportPalette());

    $this->actingAs($reporter)
        ->postJson(reportRoute($comment), ['reason' => 'spam'])
        ->assertNotFound();

    expect(ColorCommentReport::query()->count())->toBe(0);
});

it('limita denuncias por conta', function () {
    config(['colors.rates.report_per_minute' => 1]);

    $reporter = User::factory()->create();
    $palette = reportPalette();
    $first = reportableComment($palette);
    $second = reportableComment($palette);

    $this->actingAs($reporter)
        ->postJson(reportRoute($first), ['reason' => 'spam'])
        ->assertCreated();

    $this->actingAs($reporter)
        ->postJson(reportRoute($second), ['reason' => 'spam'])
        ->assertStatus(429);

    expect(ColorCommentReport::query()->count())->toBe(1);
});

/*
| Admin (F7, ADR-141).
*/

it('fecha a tela de denuncias para visitante e usuario comum', function () {
    $this->get(route('admin.comment-reports.index'))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.comment-reports.index'))
        ->assertForbidden();
});

it('lista as denuncias com o comentario e o filtro de status', function () {
    $admin = User::factory()->admin()->create();
    $palette = ColorPalette::factory()->public()->create([
        'slug' => 'paleta-denunciada',
        'name' => 'Paleta denunciada',
    ]);
    $comment = reportableComment($palette, ['body' => 'Texto denunciado']);

    $pending = ColorCommentReport::factory()
        ->for($comment, 'comment')
        ->create(['reason' => ColorCommentReportReason::Spam]);
    ColorCommentReport::factory()
        ->for($comment, 'comment')
        ->dismissed($admin)
        ->create();

    $this->actingAs($admin)
        ->get(route('admin.comment-reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CommentReports')
            ->has('reports.data', 1)
            ->where('reports.data.0.id', $pending->id)
            ->where('reports.data.0.reason', 'spam')
            ->where('reports.data.0.status', 'pending')
            ->where('reports.data.0.comment.body', 'Texto denunciado')
            ->where('reports.data.0.comment.paletteName', 'Paleta denunciada')
            ->where('filters.status', 'pending'));

    $this->actingAs($admin)
        ->get(route('admin.comment-reports.index', ['status' => 'dismissed']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('reports.data', 1)
            ->where('reports.data.0.status', 'dismissed')
            ->where('filters.status', 'dismissed'));
});

it('resolver e dispensar gravam a decisao e a auditoria', function () {
    $admin = User::factory()->admin()->create();
    $comment = reportableComment(reportPalette());

    $resolve = ColorCommentReport::factory()->for($comment, 'comment')->create();
    $dismiss = ColorCommentReport::factory()->for($comment, 'comment')->create();

    $this->actingAs($admin)
        ->post(route('admin.comment-reports.resolve', $resolve))
        ->assertRedirect(route('admin.comment-reports.index'));

    $this->actingAs($admin)
        ->post(route('admin.comment-reports.dismiss', $dismiss))
        ->assertRedirect(route('admin.comment-reports.index'));

    $resolve->refresh();
    $dismiss->refresh();

    expect($resolve->status)->toBe(ColorCommentReportStatus::Resolved)
        ->and($resolve->resolved_by)->toBe($admin->id)
        ->and($resolve->resolved_at)->not->toBeNull()
        ->and($dismiss->status)->toBe(ColorCommentReportStatus::Dismissed)
        ->and($dismiss->resolved_by)->toBe($admin->id);

    $actions = AdminAction::query()->orderBy('id')->get();

    expect($actions)->toHaveCount(2)
        ->and($actions[0]->action)->toBe(AdminActionType::CommentReportResolved)
        ->and($actions[0]->meta['id'])->toBe($resolve->id)
        ->and($actions[1]->action)->toBe(AdminActionType::CommentReportDismissed)
        ->and($actions[1]->meta['to'])->toBe('dismissed');
});
