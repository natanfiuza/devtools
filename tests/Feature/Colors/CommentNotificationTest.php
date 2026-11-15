<?php

use App\Actions\Colors\ApplyModerationResult;
use App\Actions\Colors\NotifyApprovedComment;
use App\Enums\ColorCommentStatus;
use App\Models\ColorNotificationPreference;
use App\Models\ColorPalette;
use App\Models\ColorPaletteComment;
use App\Models\ColorPaletteMute;
use App\Models\User;
use App\Notifications\Colors\CommentReplyApproved;
use App\Notifications\Colors\PaletteCommentApproved;
use App\Support\Colors\Moderation\ModerationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
| Avisos de comentario (F7, ADR-142).
|
| Aprovado avisa o dono da paleta e, numa resposta, o autor do pai - nunca o
| autor da acao, nunca duas vezes pelo mesmo comentario. Rejeitado e flagged
| nao avisam. O silencio da paleta, a conta suspensa/anonimizada e o toggle de
| e-mail sao decididos no envio, em `via()`.
*/

beforeEach(function () {
    config(['colors.comments.enabled' => true]);
    Queue::fake();
    Cache::flush();
    Notification::fake();
});

function notifyPalette(User $owner): ColorPalette
{
    return ColorPalette::factory()->public()->create(['user_id' => $owner->id]);
}

function pendingComment(ColorPalette $palette, User $author, array $attributes = []): ColorPaletteComment
{
    return ColorPaletteComment::factory()
        ->for($palette, 'palette')
        ->create(['user_id' => $author->id, ...$attributes]);
}

function applyAsApproved(ColorPaletteComment $comment): bool
{
    return app(ApplyModerationResult::class)(
        $comment->id,
        $comment->revision,
        new ModerationResult(ColorCommentStatus::Approved),
    );
}

it('avisa o dono da paleta quando o comentario e aprovado', function () {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $comment = pendingComment(notifyPalette($owner), $author);

    expect(applyAsApproved($comment))->toBeTrue();

    Notification::assertSentToTimes($owner, PaletteCommentApproved::class, 1);
    Notification::assertSentTo(
        $owner,
        PaletteCommentApproved::class,
        fn (PaletteCommentApproved $notification): bool => $notification->commentId === $comment->id
            && $notification->paletteId === $comment->color_palette_id
            && $notification->actorId === $author->id,
    );

    // O autor da acao nunca recebe o proprio aviso.
    Notification::assertNotSentTo($author, PaletteCommentApproved::class);
    Notification::assertNotSentTo($author, CommentReplyApproved::class);

    expect($comment->refresh()->notified_at)->not->toBeNull();
});

it('avisa o autor do pai e deduplica quando ele tambem e o dono', function () {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $palette = notifyPalette($owner);
    $root = ColorPaletteComment::factory()
        ->for($palette, 'palette')
        ->approved()
        ->create(['user_id' => $owner->id]);
    $reply = pendingComment($palette, $author, [
        'parent_id' => $root->id,
    ]);

    applyAsApproved($reply);

    Notification::assertSentToTimes($owner, CommentReplyApproved::class, 1);
    Notification::assertNotSentTo($owner, PaletteCommentApproved::class);
    Notification::assertNotSentTo($author, CommentReplyApproved::class);
});

it('avisa as duas partes quando dono e autor do pai sao contas diferentes', function () {
    $owner = User::factory()->create();
    $parentAuthor = User::factory()->create();
    $author = User::factory()->create();
    $palette = notifyPalette($owner);
    $root = ColorPaletteComment::factory()
        ->for($palette, 'palette')
        ->approved()
        ->create(['user_id' => $parentAuthor->id]);
    $reply = pendingComment($palette, $author, ['parent_id' => $root->id]);

    applyAsApproved($reply);

    Notification::assertSentToTimes($parentAuthor, CommentReplyApproved::class, 1);
    Notification::assertSentToTimes($owner, PaletteCommentApproved::class, 1);
});

it('nao repete o aviso quando o mesmo comentario e reaprovado', function () {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $comment = pendingComment(notifyPalette($owner), $author);

    applyAsApproved($comment);

    // A edicao volta para pending e o novo resultado aprova de novo ...
    $comment->forceFill([
        'status' => ColorCommentStatus::Pending,
        'revision' => 2,
    ])->save();

    applyAsApproved($comment);

    app(NotifyApprovedComment::class)($comment->id);

    Notification::assertSentToTimes($owner, PaletteCommentApproved::class, 1);
});

it('nao avisa nada quando o comentario e rejeitado', function () {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $comment = pendingComment(notifyPalette($owner), $author);

    app(ApplyModerationResult::class)(
        $comment->id,
        $comment->revision,
        new ModerationResult(ColorCommentStatus::Rejected),
    );

    Notification::assertNothingSent();
});

it('nao avisa quando o dono silenciou a paleta', function () {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $palette = notifyPalette($owner);
    $comment = pendingComment($palette, $author);
    ColorPaletteMute::factory()->create([
        'color_palette_id' => $palette->id,
        'user_id' => $owner->id,
    ]);

    applyAsApproved($comment);

    Notification::assertNotSentTo($owner, PaletteCommentApproved::class);
    expect((new PaletteCommentApproved(
        paletteId: $palette->id,
        paletteName: $palette->name,
        paletteSlug: $palette->slug,
        commentId: $comment->id,
        actorId: $author->id,
        actorName: $author->name,
        actorUsername: $author->username,
    ))->via($owner))->toBe([]);
});

it('nao avisa conta suspensa nem conta anonimizada', function () {
    $suspended = User::factory()->create(['suspended_at' => now()]);
    $anonymized = User::factory()->create(['anonymized_at' => now()]);
    $author = User::factory()->create();

    applyAsApproved(pendingComment(notifyPalette($suspended), $author));
    applyAsApproved(pendingComment(notifyPalette($anonymized), $author));

    Notification::assertNothingSent();
});

it('usa o toggle de e-mail do dono no envio', function () {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $comment = pendingComment(notifyPalette($owner), $author);

    // Sem linha vale o default: comentario chega tambem por e-mail.
    applyAsApproved($comment);

    Notification::assertSentTo(
        $owner,
        PaletteCommentApproved::class,
        fn (PaletteCommentApproved $notification): bool => $notification->via($owner) === ['database', 'mail'],
    );

    $second = pendingComment($comment->palette, $author);

    ColorNotificationPreference::query()->create([
        'user_id' => $owner->id,
        'email_on_like' => false,
        'email_on_follow' => false,
        'email_on_comment' => false,
        'email_on_reply' => true,
    ]);

    applyAsApproved($second);

    Notification::assertSentTo(
        $owner,
        PaletteCommentApproved::class,
        fn (PaletteCommentApproved $notification): bool => $notification->commentId === $second->id
            && $notification->via($owner) === ['database'],
    );
});

it('leva o e-mail ao comentario no idioma da conta', function () {
    $owner = User::factory()->create(['locale' => 'fr']);
    $author = User::factory()->create();
    $comment = pendingComment(notifyPalette($owner), $author);

    applyAsApproved($comment);

    $mail = (new PaletteCommentApproved(
        paletteId: $comment->color_palette_id,
        paletteName: $comment->palette->name,
        paletteSlug: $comment->palette->slug,
        commentId: $comment->id,
        actorId: $author->id,
        actorName: $author->name,
        actorUsername: $author->username,
    ))->toMail($owner);

    expect($mail->actionUrl)
        ->toContain('/fr/colors/p/')
        ->toContain('#comment-'.$comment->id);
});

it('avisa o autor do pai mesmo que o dono tenha silenciado a paleta', function () {
    $owner = User::factory()->create();
    $parentAuthor = User::factory()->create();
    $author = User::factory()->create();
    $palette = notifyPalette($owner);
    $root = ColorPaletteComment::factory()
        ->for($palette, 'palette')
        ->approved()
        ->create(['user_id' => $parentAuthor->id]);
    $reply = pendingComment($palette, $author, ['parent_id' => $root->id]);

    ColorPaletteMute::factory()->create([
        'color_palette_id' => $palette->id,
        'user_id' => $owner->id,
    ]);

    applyAsApproved($reply);

    Notification::assertSentToTimes($parentAuthor, CommentReplyApproved::class, 1);
    Notification::assertNotSentTo($owner, PaletteCommentApproved::class);
});
