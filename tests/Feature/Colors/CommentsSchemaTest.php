<?php

use App\Enums\ColorCommentReportStatus;
use App\Enums\ColorCommentStatus;
use App\Models\AiCache;
use App\Models\ColorCommentReport;
use App\Models\ColorNotificationPreference;
use App\Models\ColorPalette;
use App\Models\ColorPaletteComment;
use App\Models\ColorPaletteMute;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
| Base da F7 (ADR-139 a ADR-142).
|
| Comentarios aceitam resposta apenas para raiz, guardam a ultima versao
| aprovada e aplicam a regra de exibicao; denuncias sao unicas por par;
| preferencias e mutes tem o formato que o envio consulta.
*/

it('cria as tabelas e as colunas da F7', function () {
    expect(Schema::hasTable('color_palette_comments'))->toBeTrue()
        ->and(Schema::hasTable('color_comment_reports'))->toBeTrue()
        ->and(Schema::hasTable('color_notification_preferences'))->toBeTrue()
        ->and(Schema::hasTable('color_palette_mutes'))->toBeTrue();

    expect(Schema::hasColumns('color_palette_comments', [
        'parent_id', 'user_id', 'body', 'approved_body', 'status', 'locale',
        'revision', 'moderation_reason', 'moderated_at', 'notified_at',
        'edited_at', 'deleted_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('ai_cache', ['purpose', 'expires_at']))->toBeTrue();
});

it('aceita expires_at nulo no cache e trata a linha como permanente', function () {
    $cache = AiCache::factory()->permanent()->create();

    expect($cache->purpose)->toBe('ai')
        ->and($cache->expires_at)->toBeNull()
        ->and($cache->isExpired())->toBeFalse();
});

it('encadeia comentario, resposta e denuncia', function () {
    $root = ColorPaletteComment::factory()->approved()->create();
    $reply = ColorPaletteComment::factory()->replyTo($root)->approved()->create();

    $report = ColorCommentReport::factory()->create([
        'comment_id' => $reply->id,
        'status' => ColorCommentReportStatus::Pending,
    ]);

    expect($reply->fresh()->parent_id)->toBe($root->id)
        ->and($reply->isRoot())->toBeFalse()
        ->and($root->canBeRepliedTo())->toBeTrue()
        ->and($reply->canBeRepliedTo())->toBeFalse()
        ->and($report->comment->id)->toBe($reply->id)
        ->and($root->replies()->count())->toBe(1);
});

it('impede duas denuncias do mesmo denunciante', function () {
    $user = User::factory()->create();
    $comment = ColorPaletteComment::factory()->approved()->create();

    ColorCommentReport::factory()->create([
        'comment_id' => $comment->id,
        'user_id' => $user->id,
    ]);

    expect(fn () => ColorCommentReport::factory()->create([
        'comment_id' => $comment->id,
        'user_id' => $user->id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('aplica a regra de exibicao por status', function () {
    $approved = ColorPaletteComment::factory()->approved()->create(['body' => 'ok']);
    $pending = ColorPaletteComment::factory()->create(['body' => 'novo']);
    $flagged = ColorPaletteComment::factory()->flagged()->create(['body' => 'revisar']);

    $rejectedNew = ColorPaletteComment::factory()->rejected()->create([
        'body' => 'ruim',
        'approved_body' => null,
    ]);

    $rejectedEdit = ColorPaletteComment::factory()->rejected()->create([
        'body' => 'ruim de novo',
        'approved_body' => 'texto aprovado',
    ]);

    $deleted = ColorPaletteComment::factory()->approved()->deleted()->create();

    expect($approved->displayBody())->toBe('ok')
        ->and($pending->awaitsReview())->toBeTrue()
        ->and($pending->displayBody())->toBe('novo')
        ->and($flagged->awaitsReview())->toBeTrue()
        ->and($flagged->isVisibleToPublic())->toBeTrue()
        ->and($rejectedNew->displayBody())->toBeNull()
        ->and($rejectedNew->isVisibleToPublic())->toBeFalse()
        ->and($rejectedEdit->displayBody())->toBe('texto aprovado')
        ->and($rejectedEdit->isVisibleToPublic())->toBeTrue()
        ->and($deleted->displayBody())->toBeNull()
        ->and($deleted->isVisibleToPublic())->toBeFalse();

    expect($approved->status)->toBe(ColorCommentStatus::Approved);
});

it('grava preferencias e mutes por conta e paleta', function () {
    $owner = User::factory()->create();
    $palette = ColorPalette::factory()->for($owner)->public()->create();

    $preferences = ColorNotificationPreference::for($owner);

    expect($preferences->email_on_comment)->toBeTrue()
        ->and($preferences->email_on_like)->toBeFalse()
        ->and($preferences->allowsEmail('reply'))->toBeTrue()
        ->and($preferences->allowsEmail('follow'))->toBeFalse();

    ColorPaletteMute::create([
        'color_palette_id' => $palette->id,
        'user_id' => $owner->id,
    ]);

    expect(ColorPaletteMute::isMuted($owner, $palette->id))->toBeTrue();
});
