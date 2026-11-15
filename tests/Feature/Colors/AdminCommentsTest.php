<?php

use App\Enums\AdminActionType;
use App\Enums\ColorCommentReportStatus;
use App\Enums\ColorCommentStatus;
use App\Models\AdminAction;
use App\Models\ColorCommentReport;
use App\Models\ColorPalette;
use App\Models\ColorPaletteComment;
use App\Models\User;
use App\Notifications\Colors\PaletteCommentApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Moderacao de comentarios no painel (F7, ADR-139/ADR-140).
|
| O admin ve o texto - inclusive do excluido -, porque e ele que sustenta a
| decisao; o texto nunca vai para log nem para a auditoria. Aprovar publica e
| avisa os interessados; rejeitar e excluir encerram as denuncias pendentes. Toda
| decisao bumpa `revision` e invalida jobs de moderacao em voo.
*/

beforeEach(function () {
    config(['colors.comments.enabled' => true]);
    Queue::fake();
    Cache::flush();
    Notification::fake();
});

function moderationPalette(): ColorPalette
{
    return ColorPalette::factory()->public()->create([
        'name' => 'Paleta moderada',
    ]);
}

function moderationComment(ColorPalette $palette, array $attributes = []): ColorPaletteComment
{
    return ColorPaletteComment::factory()
        ->for($palette, 'palette')
        ->create($attributes);
}

it('fecha a moderacao para visitante e usuario comum', function () {
    $comment = moderationComment(moderationPalette());

    $this->get(route('admin.comments.index'))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.comments.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->post(route('admin.comments.approve', $comment))
        ->assertForbidden();

    expect(AdminAction::query()->count())->toBe(0);
});

it('lista a fila com filtro de status, busca e contagem de denuncias', function () {
    $admin = User::factory()->admin()->create();
    $palette = moderationPalette();
    $pending = moderationComment($palette, ['body' => 'Primeiro texto']);
    moderationComment($palette, [
        'body' => 'Texto aprovado',
        'status' => ColorCommentStatus::Approved,
        'approved_body' => 'Texto aprovado',
    ]);
    $flagged = ColorPaletteComment::factory()
        ->for($palette, 'palette')
        ->flagged()
        ->create(['body' => 'Precisa de analise manual']);
    ColorCommentReport::factory()->for($pending, 'comment')->create();

    $this->actingAs($admin)
        ->get(route('admin.comments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CommentModeration')
            ->has('comments.data', 1)
            ->where('comments.data.0.id', $pending->id)
            ->where('comments.data.0.body', 'Primeiro texto')
            ->where('comments.data.0.status', 'pending')
            ->where('comments.data.0.deleted', false)
            ->where('comments.data.0.paletteName', 'Paleta moderada')
            ->where('comments.data.0.pendingReports', 1)
            ->where('filters.status', 'pending')
            ->where('filters.q', ''));

    $this->actingAs($admin)
        ->get(route('admin.comments.index', ['status' => 'flagged']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments.data', 1)
            ->where('comments.data.0.id', $flagged->id)
            ->where('filters.status', 'flagged'));

    $this->actingAs($admin)
        ->get(route('admin.comments.index', ['status' => 'all', 'q' => 'aprovado']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments.data', 1)
            ->where('comments.data.0.body', 'Texto aprovado')
            ->where('filters.q', 'aprovado'));
});

it('aprova, grava a versao aprovada, avisa o dono e audita sem o texto', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $palette = ColorPalette::factory()->public()->create([
        'user_id' => $owner->id,
        'name' => 'Oceano',
    ]);
    $comment = ColorPaletteComment::factory()
        ->for($palette, 'palette')
        ->create([
            'user_id' => User::factory(),
            'body' => 'Comentario esperando aprovacao',
        ]);

    $this->actingAs($admin)
        ->post(route('admin.comments.approve', $comment))
        ->assertRedirect(route('admin.comments.index'));

    $comment->refresh();

    expect($comment->status)->toBe(ColorCommentStatus::Approved)
        ->and($comment->approved_body)->toBe('Comentario esperando aprovacao')
        ->and($comment->moderated_at)->not->toBeNull()
        ->and($comment->revision)->toBe(2);

    Notification::assertSentTo($owner, PaletteCommentApproved::class);

    $action = AdminAction::query()->sole();

    expect($action->action)->toBe(AdminActionType::CommentApproved)
        ->and($action->target_id)->toBeNull()
        ->and($action->meta['id'])->toBe($comment->id)
        ->and($action->meta['palette_id'])->toBe($palette->id)
        ->and($action->meta['from'])->toBe('pending')
        ->and($action->meta['to'])->toBe('approved')
        ->and(json_encode($action->meta))->not->toContain('Comentario esperando aprovacao');

    // Aprovar de novo nao bumpa a revisao, nao reaudita e nao reavisa.
    $this->actingAs($admin)
        ->post(route('admin.comments.approve', $comment))
        ->assertRedirect(route('admin.comments.index'));

    expect($comment->refresh()->revision)->toBe(2)
        ->and(AdminAction::query()->count())->toBe(1);

    Notification::assertSentToTimes($owner, PaletteCommentApproved::class, 1);
});

it('rejeita, encerra as denuncias pendentes e audita', function () {
    $admin = User::factory()->admin()->create();
    $comment = moderationComment(moderationPalette(), [
        'body' => 'Texto abusivo',
        'status' => ColorCommentStatus::Flagged,
    ]);
    $report = ColorCommentReport::factory()->for($comment, 'comment')->create();

    $this->actingAs($admin)
        ->post(route('admin.comments.reject', $comment))
        ->assertRedirect(route('admin.comments.index'));

    $comment->refresh();
    $report->refresh();

    expect($comment->status)->toBe(ColorCommentStatus::Rejected)
        ->and($comment->moderation_reason)->toBe('policy')
        ->and($comment->approved_body)->toBeNull()
        ->and($comment->moderated_at)->not->toBeNull()
        ->and($comment->revision)->toBe(2)
        ->and($report->status)->toBe(ColorCommentReportStatus::Resolved)
        ->and($report->resolved_at)->not->toBeNull();

    $action = AdminAction::query()->sole();

    expect($action->action)->toBe(AdminActionType::CommentRejected)
        ->and($action->meta['from'])->toBe('flagged')
        ->and($action->meta['to'])->toBe('rejected')
        ->and(json_encode($action->meta))->not->toContain('Texto abusivo');

    Notification::assertNothingSent();
});

it('exclui com tombstone, encerra denuncias e mantem o texto so na tela', function () {
    $admin = User::factory()->admin()->create();
    $comment = moderationComment(moderationPalette(), [
        'body' => 'Texto que sera excluido',
        'status' => ColorCommentStatus::Approved,
        'approved_body' => 'Texto que sera excluido',
    ]);
    $report = ColorCommentReport::factory()->for($comment, 'comment')->create();

    $this->actingAs($admin)
        ->delete(route('admin.comments.destroy', $comment))
        ->assertRedirect(route('admin.comments.index'));

    $comment->refresh();
    $report->refresh();

    expect($comment->deleted_at)->not->toBeNull()
        ->and($comment->revision)->toBe(2)
        ->and($report->status)->toBe(ColorCommentReportStatus::Resolved);

    $action = AdminAction::query()->sole();

    expect($action->action)->toBe(AdminActionType::CommentDeleted)
        ->and($action->meta['from'])->toBe('approved')
        ->and($action->meta['to'])->toBe('deleted')
        ->and(json_encode($action->meta))->not->toContain('Texto que sera excluido');

    $this->actingAs($admin)
        ->get(route('admin.comments.index', ['status' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments.data', 1)
            ->where('comments.data.0.id', $comment->id)
            ->where('comments.data.0.deleted', true)
            ->where('comments.data.0.body', 'Texto que sera excluido'));
});

it('nao redecide nem audita comentario ja excluido', function () {
    $admin = User::factory()->admin()->create();
    $comment = ColorPaletteComment::factory()
        ->for(moderationPalette(), 'palette')
        ->deleted()
        ->create([
            'body' => 'Fora do ar',
            'status' => ColorCommentStatus::Pending,
        ]);

    $this->actingAs($admin)
        ->post(route('admin.comments.approve', $comment))
        ->assertRedirect(route('admin.comments.index'));

    $this->actingAs($admin)
        ->post(route('admin.comments.reject', $comment))
        ->assertRedirect(route('admin.comments.index'));

    $this->actingAs($admin)
        ->delete(route('admin.comments.destroy', $comment))
        ->assertRedirect(route('admin.comments.index'));

    expect($comment->refresh()->status)->toBe(ColorCommentStatus::Pending)
        ->and($comment->revision)->toBe(1)
        ->and(AdminAction::query()->count())->toBe(0);

    Notification::assertNothingSent();
});
