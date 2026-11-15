<?php

use App\Enums\ColorCommentStatus;
use App\Jobs\ModerateColorComment;
use App\Models\ColorPalette;
use App\Models\ColorPaletteComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
| Escrita da thread de comentarios (F7, ADR-139/ADR-140).
|
| Comentar exige conta; paleta privada, comentario de outra conta e revisao
| oculta respondem o mesmo 404. O comentario nasce pendente, o filtro local
| barra antes de gravar e a moderacao roda depois do commit sem cobrar cota.
*/

beforeEach(function () {
    config(['colors.comments.enabled' => true]);
    Queue::fake();
    Cache::flush();
});

function publicPalette(string $slug = 'publica'): ColorPalette
{
    return ColorPalette::factory()->public()->create(['slug' => $slug]);
}

it('exige conta para comentar', function () {
    $palette = publicPalette();

    $this->postJson(route('api.tools.colors.palettes.comments.store', $palette), [
        'body' => 'Ola',
    ])->assertUnauthorized();

    expect(ColorPaletteComment::query()->count())->toBe(0);
});

it('cria comentario pendente e devolve apenas os campos publicos', function () {
    $author = User::factory()->create();
    $palette = publicPalette();

    $response = $this->actingAs($author)
        ->postJson(route('api.tools.colors.palettes.comments.store', $palette), [
            'body' => '  Que paleta linda  ',
        ])
        ->assertCreated()
        ->assertJsonPath('comment.body', 'Que paleta linda')
        ->assertJsonPath('comment.pending', true)
        ->assertJsonPath('comment.deleted', false)
        ->assertJsonPath('comment.can_edit', true)
        ->assertJsonPath('comment.can_delete', true)
        ->assertJsonPath('comment.can_report', false)
        ->assertJsonPath('comment.author.id', $author->id)
        ->assertJsonPath('comment.replies', [])
        ->assertJsonMissingPath('comment.user_id')
        ->assertJsonMissingPath('comment.revision')
        ->assertJsonMissingPath('comment.approved_body')
        ->assertJsonMissingPath('comment.moderation_reason');

    $comment = ColorPaletteComment::query()->sole();

    expect($comment->status)->toBe(ColorCommentStatus::Pending)
        ->and($comment->revision)->toBe(1)
        ->and($comment->body)->toBe('Que paleta linda')
        ->and($comment->approved_body)->toBeNull()
        ->and($comment->parent_id)->toBeNull()
        ->and($response->json('comment.id'))->toBe($comment->id);

    Queue::assertPushed(
        ModerateColorComment::class,
        fn (ModerateColorComment $job): bool => $job->commentId === $comment->id
            && $job->revision === 1,
    );
});

it('responde uma raiz da mesma paleta', function () {
    $author = User::factory()->create();
    $palette = publicPalette();
    $root = ColorPaletteComment::factory()->for($palette, 'palette')->create();

    $this->actingAs($author)
        ->postJson(route('api.tools.colors.palettes.comments.store', $palette), [
            'body' => 'Concordo',
            'parent_id' => $root->id,
        ])
        ->assertCreated()
        ->assertJsonPath('comment.pending', true);

    $reply = ColorPaletteComment::query()->whereNotNull('parent_id')->sole();

    expect($reply->parent_id)->toBe($root->id)
        ->and($reply->color_palette_id)->toBe($palette->id);

    Queue::assertPushed(ModerateColorComment::class, 1);
});

it('recusa resposta a resposta, a pai de outra paleta e a pai inexistente', function () {
    $author = User::factory()->create();
    $palette = publicPalette();
    $root = ColorPaletteComment::factory()->for($palette, 'palette')->create();
    $reply = ColorPaletteComment::factory()->replyTo($root)->create();
    $foreign = ColorPaletteComment::factory()->create();

    $this->actingAs($author)
        ->postJson(route('api.tools.colors.palettes.comments.store', $palette), [
            'body' => 'Terceiro nivel',
            'parent_id' => $reply->id,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.errors.parent_id.0', __('colors.validation.comment_parent_invalid'));

    $this->actingAs($author)
        ->postJson(route('api.tools.colors.palettes.comments.store', $palette), [
            'body' => 'Outra paleta',
            'parent_id' => $foreign->id,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.errors.parent_id.0', __('colors.validation.comment_parent_invalid'));

    $this->actingAs($author)
        ->postJson(route('api.tools.colors.palettes.comments.store', $palette), [
            'body' => 'Fantasma',
            'parent_id' => (string) Str::ulid(),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.errors.parent_id.0', __('colors.validation.comment_parent_invalid'));

    expect(ColorPaletteComment::query()->count())->toBe(3);
});

it('valida corpo vazio e o teto configurado', function () {
    config(['colors.comments.body_max_length' => 10]);

    $author = User::factory()->create();
    $palette = publicPalette();

    $this->actingAs($author)
        ->postJson(route('api.tools.colors.palettes.comments.store', $palette), ['body' => '   '])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.errors.body.0', __('colors.validation.comment_body_required'));

    $this->actingAs($author)
        ->postJson(route('api.tools.colors.palettes.comments.store', $palette), [
            'body' => str_repeat('a', 11),
        ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'error.details.errors.body.0',
            __('colors.validation.comment_body_max', ['max' => 10]),
        );

    expect(ColorPaletteComment::query()->count())->toBe(0);
});

it('bloqueia o termo local sem gravar nem revelar o termo', function () {
    $author = User::factory()->create();
    $palette = publicPalette();

    $this->actingAs($author)
        ->postJson(route('api.tools.colors.palettes.comments.store', $palette), [
            'body' => 'This is shit',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.details.errors.body.0', __('colors.validation.comment_blocked'));

    expect(ColorPaletteComment::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});

it('recusa comentario em paleta privada, arquivada ou desconhecida', function () {
    $author = User::factory()->create();

    $this->actingAs($author)
        ->postJson(route('api.tools.colors.palettes.comments.store', ColorPalette::factory()->create(['slug' => 'privada'])), [
            'body' => 'Ola',
        ])
        ->assertNotFound();

    $archived = ColorPalette::factory()->public()->archived()->create(['slug' => 'arquivada']);

    $this->actingAs($author)
        ->postJson(route('api.tools.colors.palettes.comments.store', $archived), [
            'body' => 'Ola',
        ])
        ->assertNotFound();

    $this->actingAs($author)
        ->postJson(route('api.tools.colors.palettes.comments.store', (string) Str::ulid()), [
            'body' => 'Ola',
        ])
        ->assertNotFound();

    expect(ColorPaletteComment::query()->count())->toBe(0);
});

it('so o autor edita e a edicao volta para a moderacao', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $comment = ColorPaletteComment::factory()->for($author, 'author')->approved()->create();

    $this->actingAs($other)
        ->patchJson(route('api.tools.colors.comments.update', $comment), [
            'body' => 'Invasao',
        ])
        ->assertNotFound();

    $this->actingAs($author)
        ->patchJson(route('api.tools.colors.comments.update', $comment), [
            'body' => '  Texto novo  ',
        ])
        ->assertOk()
        ->assertJsonPath('comment.body', 'Texto novo')
        ->assertJsonPath('comment.pending', true)
        ->assertJsonPath('comment.edited_at', fn ($value): bool => $value !== null);

    $comment->refresh();

    expect($comment->body)->toBe('Texto novo')
        ->and($comment->status)->toBe(ColorCommentStatus::Pending)
        ->and($comment->revision)->toBe(2)
        ->and($comment->moderated_at)->toBeNull()
        ->and($comment->approved_body)->not->toBeNull();

    Queue::assertPushed(
        ModerateColorComment::class,
        fn (ModerateColorComment $job): bool => $job->commentId === $comment->id
            && $job->revision === 2,
    );
});

it('nao edita comentario excluido', function () {
    $author = User::factory()->create();
    $comment = ColorPaletteComment::factory()->for($author, 'author')->deleted()->create();

    $this->actingAs($author)
        ->patchJson(route('api.tools.colors.comments.update', $comment), [
            'body' => 'Ressuscitar',
        ])
        ->assertNotFound();
});

it('exclui sem respostas e some da thread', function () {
    $author = User::factory()->create();
    $palette = publicPalette();
    $comment = ColorPaletteComment::factory()
        ->for($palette, 'palette')
        ->for($author, 'author')
        ->create();

    $this->actingAs($author)
        ->deleteJson(route('api.tools.colors.comments.destroy', $comment))
        ->assertOk()
        ->assertJsonPath('deleted', true)
        ->assertJsonPath('tombstone', false)
        ->assertJsonPath('comment', null);

    expect($comment->fresh()->trashed())->toBeTrue();
});

it('preserva tombstone quando a raiz tem respostas', function () {
    $author = User::factory()->create();
    $palette = publicPalette();
    $root = ColorPaletteComment::factory()
        ->for($palette, 'palette')
        ->for($author, 'author')
        ->create();
    ColorPaletteComment::factory()->replyTo($root)->create();

    $this->actingAs($author)
        ->deleteJson(route('api.tools.colors.comments.destroy', $root))
        ->assertOk()
        ->assertJsonPath('tombstone', true)
        ->assertJsonPath('comment.deleted', true)
        ->assertJsonPath('comment.id', $root->id)
        ->assertJsonMissingPath('comment.user_id')
        ->assertJsonMissingPath('comment.approved_body');

    expect($root->fresh()->trashed())->toBeTrue();
});

it('recusa excluir comentario de outra conta', function () {
    $other = User::factory()->create();
    $comment = ColorPaletteComment::factory()->create();

    $this->actingAs($other)
        ->deleteJson(route('api.tools.colors.comments.destroy', $comment))
        ->assertNotFound();

    expect($comment->fresh()->trashed())->toBeFalse();
});

it('limita comentarios por conta', function () {
    config(['colors.rates.comment_per_minute' => 1]);

    $author = User::factory()->create();
    $palette = publicPalette();

    $this->actingAs($author)
        ->postJson(route('api.tools.colors.palettes.comments.store', $palette), ['body' => 'Um'])
        ->assertCreated();

    $this->actingAs($author)
        ->postJson(route('api.tools.colors.palettes.comments.store', $palette), ['body' => 'Dois'])
        ->assertStatus(429)
        ->assertJsonPath('message', __('colors.validation.rate_limited'));

    expect(ColorPaletteComment::query()->count())->toBe(1);
});
