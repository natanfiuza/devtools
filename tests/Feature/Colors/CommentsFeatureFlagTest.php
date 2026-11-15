<?php

use App\Enums\ColorCommentStatus;
use App\Models\ColorPalette;
use App\Models\ColorPaletteComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Interruptor dos comentarios (F7, ADR-143).
|
| Desligado por padrao: a pagina publica continua abrindo, mas sem thread nas
| props, e comentario/denuncia/mute respondem 404 uniforme. O aceite da fase
| so liga a flag por decisao explicita.
*/

beforeEach(function () {
    Queue::fake();
});

it('desliga a thread por padrao sem quebrar a pagina publica', function () {
    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);
    $comment = ColorPaletteComment::factory()->for($palette, 'palette')->create();

    $this->get('/en/colors/p/publica')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('colors/PublicPalette')
            ->where('palette.slug', 'publica')
            ->where('comments', null)
            ->where('canComment', false)
            ->where('commentForm', null)
        );

    $author = User::factory()->create();

    $this->actingAs($author)
        ->postJson(route('api.tools.colors.palettes.comments.store', $palette), [
            'body' => 'Bloqueado',
        ])->assertNotFound();

    $this->actingAs($author)
        ->patchJson(route('api.tools.colors.comments.update', $comment), [
            'body' => 'Bloqueado',
        ])->assertNotFound();

    $this->actingAs($author)
        ->deleteJson(route('api.tools.colors.comments.destroy', $comment))
        ->assertNotFound();
});

it('entrega a thread e os limites quando ligado', function () {
    config(['colors.comments.enabled' => true, 'colors.comments.body_max_length' => 255]);

    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);
    $comment = ColorPaletteComment::factory()
        ->for($palette, 'palette')
        ->create([
            'body' => 'Visivel',
            'status' => ColorCommentStatus::Approved,
            'approved_body' => 'Visivel',
        ]);

    $this->get('/en/colors/p/publica')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments.data', 1)
            ->where('comments.data.0.id', $comment->id)
            ->where('comments.data.0.body', 'Visivel')
            ->where('comments.data.0.pending', false)
            ->missing('comments.data.0.user_id')
            ->missing('comments.data.0.revision')
            ->where('canComment', false)
            ->where('commentForm.body_max_length', 255)
        );
});

it('so libera o formulario para a conta logada', function () {
    config(['colors.comments.enabled' => true]);

    $user = User::factory()->create();
    ColorPalette::factory()->public()->create(['slug' => 'publica']);

    $this->actingAs($user)
        ->get('/en/colors/p/publica')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canComment', true)
            ->where('commentForm.body_max_length', 255)
        );
});

it('pagina a thread pela query comments_page', function () {
    config(['colors.comments.enabled' => true, 'colors.comments.per_page' => 1]);

    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);

    ColorPaletteComment::factory()->for($palette, 'palette')->create([
        'body' => 'Antiga',
        'created_at' => now()->subMinute(),
    ]);
    ColorPaletteComment::factory()->for($palette, 'palette')->create([
        'body' => 'Nova',
        'created_at' => now(),
    ]);

    $this->get('/en/colors/p/publica?comments_page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('comments.current_page', 2)
            ->where('comments.last_page', 2)
            ->where('comments.data.0.body', 'Antiga')
        );
});
