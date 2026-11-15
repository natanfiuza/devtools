<?php

use App\Enums\ColorCommentStatus;
use App\Models\ColorPalette;
use App\Models\ColorPaletteComment;
use App\Models\User;
use App\Support\Colors\CommentPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Visibilidade da thread (F7, ADR-139/ADR-140).
|
| Aprovado e definitivo; pendente e flagged aparecem com o selo "em analise";
| rejeitado so reaparece com o texto aprovado anterior. Raiz excluida com
| respostas vira tombstone, resposta excluida some e autor invisivel vira
| "conta removida". Nada de campos internos sai do presenter.
*/

beforeEach(function () {
    config([
        'colors.comments.enabled' => true,
        'colors.comments.per_page' => 20,
        'colors.comments.replies_limit' => 100,
    ]);
});

function threadItems(ColorPalette $palette, ?User $viewer = null): array
{
    return app(CommentPresenter::class)->thread($palette, $viewer, 1)->items();
}

function commentFor(ColorPalette $palette, string $body, array $attributes = []): ColorPaletteComment
{
    return ColorPaletteComment::factory()
        ->for($palette, 'palette')
        ->create(['body' => $body, ...$attributes]);
}

it('mostra aprovado, pendente e flagged e esconde o rejeitado sem versao aprovada', function () {
    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);

    $approved = commentFor($palette, 'Aprovado', [
        'status' => ColorCommentStatus::Approved,
        'approved_body' => 'Aprovado',
    ]);
    $pending = commentFor($palette, 'Pendente');
    $flagged = commentFor($palette, 'Flagged', ['status' => ColorCommentStatus::Flagged]);
    commentFor($palette, 'Rejeitado', ['status' => ColorCommentStatus::Rejected]);

    $items = collect(threadItems($palette))->keyBy('id');

    expect($items)->toHaveCount(3)
        ->and($items[$approved->id]['pending'])->toBeFalse()
        ->and($items[$pending->id]['pending'])->toBeTrue()
        ->and($items[$pending->id]['body'])->toBe('Pendente')
        ->and($items[$flagged->id]['pending'])->toBeTrue();
});

it('rejeitado com versao aprovada volta a mostrar o texto anterior', function () {
    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);

    $comment = commentFor($palette, 'Texto rejeitado', [
        'approved_body' => 'Texto aprovado',
        'status' => ColorCommentStatus::Rejected,
    ]);

    $items = threadItems($palette);

    expect($items)->toHaveCount(1)
        ->and($items[0]['id'])->toBe($comment->id)
        ->and($items[0]['body'])->toBe('Texto aprovado')
        ->and($items[0]['pending'])->toBeFalse();
});

it('raiz excluida so aparece como tombstone quando tem resposta viva', function () {
    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);

    $withReplies = commentFor($palette, 'Vai virar tombstone');
    ColorPaletteComment::factory()->replyTo($withReplies)->create(['body' => 'Resposta viva']);
    $withReplies->delete();

    $hidden = commentFor($palette, 'Sem respostas');
    $hidden->delete();

    $deadReplies = ColorPaletteComment::factory()->replyTo($hidden)->create();
    $deadReplies->delete();

    $items = collect(threadItems($palette))->keyBy('id');

    expect($items)->toHaveCount(1)
        ->and($items[$withReplies->id]['deleted'])->toBeTrue()
        ->and($items[$withReplies->id]['body'])->toBe('')
        ->and($items[$withReplies->id]['can_edit'])->toBeFalse()
        ->and($items[$withReplies->id]['replies'])->toHaveCount(1)
        ->and($items[$hidden->id] ?? null)->toBeNull();
});

it('nao lista resposta excluida e ordena as respostas da mais antiga', function () {
    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);
    $root = commentFor($palette, 'Raiz');

    $first = ColorPaletteComment::factory()->replyTo($root)->create([
        'body' => 'Primeira',
        'created_at' => now()->subMinutes(2),
    ]);
    $second = ColorPaletteComment::factory()->replyTo($root)->create([
        'body' => 'Segunda',
        'created_at' => now()->subMinute(),
    ]);
    $gone = ColorPaletteComment::factory()->replyTo($root)->create(['body' => 'Excluida']);
    $gone->delete();

    $items = threadItems($palette);

    expect($items[0]['replies'])->toHaveCount(2)
        ->and($items[0]['replies'][0]['id'])->toBe($first->id)
        ->and($items[0]['replies'][1]['id'])->toBe($second->id)
        ->and($items[0]['replies_truncated'])->toBeFalse();
});

it('corta as respostas no limite configurado e sinaliza a truncagem', function () {
    config(['colors.comments.replies_limit' => 1]);

    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);
    $root = commentFor($palette, 'Raiz');

    ColorPaletteComment::factory()->replyTo($root)->create(['body' => 'Primeira']);
    ColorPaletteComment::factory()->replyTo($root)->create(['body' => 'Segunda']);

    $items = threadItems($palette);

    expect($items[0]['replies'])->toHaveCount(1)
        ->and($items[0]['replies'][0]['body'])->toBe('Primeira')
        ->and($items[0]['replies_truncated'])->toBeTrue();
});

it('entrega os direitos de cada visitante', function () {
    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);
    $author = User::factory()->create();
    $other = User::factory()->create();

    $comment = commentFor($palette, 'Meu comentario', ['user_id' => $author->id]);

    $own = threadItems($palette, $author)[0];
    $stranger = threadItems($palette, $other)[0];
    $guest = threadItems($palette)[0];

    expect($own['can_edit'])->toBeTrue()
        ->and($own['can_delete'])->toBeTrue()
        ->and($own['can_report'])->toBeFalse()
        ->and($stranger['can_edit'])->toBeFalse()
        ->and($stranger['can_delete'])->toBeFalse()
        ->and($stranger['can_report'])->toBeTrue()
        ->and($guest['can_edit'])->toBeFalse()
        ->and($guest['can_report'])->toBeFalse()
        ->and($comment->id)->toBe($guest['id']);
});

it('autor suspenso, anonimizado ou removido vira conta removida', function () {
    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);

    $suspended = User::factory()->create(['suspended_at' => now()]);
    $anonymized = User::factory()->create(['anonymized_at' => now()]);

    commentFor($palette, 'Suspensa', ['user_id' => $suspended->id]);
    commentFor($palette, 'Anonima', ['user_id' => $anonymized->id]);
    commentFor($palette, 'Sem conta', ['user_id' => null]);

    foreach (threadItems($palette) as $item) {
        expect($item['author'])->toBeNull()
            ->and($item['author_removed'])->toBeTrue();
    }
});

it('nao expoe campos internos no shape publico', function () {
    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);
    commentFor($palette, 'Texto', ['revision' => 3, 'moderation_reason' => 'spam']);

    $item = threadItems($palette)[0];

    expect(array_keys($item))->toBe([
        'id',
        'body',
        'pending',
        'deleted',
        'created_at',
        'edited_at',
        'author',
        'author_removed',
        'can_edit',
        'can_delete',
        'can_report',
        'replies',
        'replies_truncated',
    ])->and($item)->not->toHaveKey('user_id')
        ->and($item)->not->toHaveKey('revision')
        ->and($item)->not->toHaveKey('approved_body')
        ->and($item)->not->toHaveKey('moderation_reason');
});

it('pagina as raizes pela query comments_page', function () {
    config(['colors.comments.per_page' => 1]);

    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);

    $older = commentFor($palette, 'Antiga', ['created_at' => now()->subMinute()]);
    $newer = commentFor($palette, 'Nova', ['created_at' => now()]);

    $presenter = app(CommentPresenter::class);
    $first = $presenter->thread($palette, null, 1);
    $second = $presenter->thread($palette, null, 2);

    expect($first->items()[0]['id'])->toBe($newer->id)
        ->and($first->lastPage())->toBe(2)
        ->and($second->items()[0]['id'])->toBe($older->id);
});
