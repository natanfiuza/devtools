<?php

use App\Models\ColorFavorite;
use App\Models\ColorHistoryEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Biblioteca pessoal de cores (F5, ADR-127).
|
| Cobre autenticacao, chave canonica, upsert do historico, importacao
| explicita e idempotente, favoritos compartilhados e o limiter de escrita.
| O anonimo continua no localStorage e nao deve alcancar estas rotas.
*/

it('redireciona visitante ao login em todas as rotas da biblioteca', function () {
    $this->getJson(route('api.tools.colors.history.index'))->assertUnauthorized();
    $this->getJson(route('api.tools.colors.favorites.index'))->assertUnauthorized();
    $this->postJson(route('api.tools.colors.history.store'), ['color' => '#11223344'])->assertUnauthorized();
    $this->deleteJson(route('api.tools.colors.history.destroy'))->assertUnauthorized();
    $this->postJson(route('api.tools.colors.history.import'), ['colors' => ['#11223344']])->assertUnauthorized();
    $this->postJson(route('api.tools.colors.favorites.store'), ['color' => '#11223344'])->assertUnauthorized();
    $this->deleteJson(route('api.tools.colors.favorites.destroy'), ['color' => '#11223344'])->assertUnauthorized();
});

it('grava a cor canonica e regravar a mesma cor nao duplica', function () {
    $user = User::factory()->create();

    $first = $this->actingAs($user)
        ->postJson(route('api.tools.colors.history.store'), ['color' => '#AABBCCDD'])
        ->assertOk()
        ->assertJsonPath('entry.color', '#aabbccdd');

    $id = $first->json('entry.id');
    $createdAt = $first->json('entry.updated_at');

    $this->travel(2)->seconds();

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.history.store'), ['color' => '#aabbccdd'])
        ->assertOk()
        ->assertJsonPath('entry.id', $id)
        ->assertJsonPath('entry.color', '#aabbccdd');

    expect(ColorHistoryEntry::query()->forUser($user->id)->count())->toBe(1);

    $entry = ColorHistoryEntry::query()->forUser($user->id)->firstOrFail();

    expect($entry->updated_at?->toIso8601String())->not->toBe($createdAt)
        ->and($entry->updated_at?->greaterThan($entry->created_at))->toBeTrue();
});

it('aceita transparente como cor canonica', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.history.store'), ['color' => 'Transparent'])
        ->assertOk()
        ->assertJsonPath('entry.color', 'transparent');
});

it('recusa formatos fora da chave canonica', function (string $color) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.history.store'), ['color' => $color])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['errors' => ['color']]]]);
})->with(['#fff', '#aabbcc', 'red', '']);

it('lista apenas o historico da propria conta, do mais recente para o mais antigo', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $older = ColorHistoryEntry::create(['user_id' => $user->id, 'color' => '#11111111']);
    $newer = ColorHistoryEntry::create(['user_id' => $user->id, 'color' => '#22222222']);
    ColorHistoryEntry::query()->whereKey($older->id)->update(['updated_at' => now()->subMinute()]);
    ColorHistoryEntry::create(['user_id' => $other->id, 'color' => '#33333333']);

    $this->actingAs($user)
        ->getJson(route('api.tools.colors.history.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('data.0.color', '#22222222');
});

it('pagina o historico por cursor', function () {
    config(['colors.library.history_per_page' => 1]);

    $user = User::factory()->create();

    $older = ColorHistoryEntry::create(['user_id' => $user->id, 'color' => '#11111111']);
    $newer = ColorHistoryEntry::create(['user_id' => $user->id, 'color' => '#22222222']);
    ColorHistoryEntry::query()->whereKey($older->id)->update(['updated_at' => now()->subMinute()]);

    $first = $this->actingAs($user)
        ->getJson(route('api.tools.colors.history.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $newer->id);

    $cursor = $first->json('next_cursor');

    expect($cursor)->toBeString();

    $this->actingAs($user)
        ->getJson(route('api.tools.colors.history.index', ['cursor' => $cursor]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $older->id)
        ->assertJsonPath('next_cursor', null);
});

it('remove uma cor ou limpa o historico inteiro', function () {
    $user = User::factory()->create();

    ColorHistoryEntry::create(['user_id' => $user->id, 'color' => '#11111111']);
    ColorHistoryEntry::create(['user_id' => $user->id, 'color' => '#22222222']);

    $this->actingAs($user)
        ->deleteJson(route('api.tools.colors.history.destroy'), ['color' => '#11111111'])
        ->assertOk()
        ->assertJsonPath('deleted', true);

    expect(ColorHistoryEntry::query()->forUser($user->id)->pluck('color')->all())
        ->toBe(['#22222222']);

    $this->actingAs($user)
        ->deleteJson(route('api.tools.colors.history.destroy'))
        ->assertOk();

    expect(ColorHistoryEntry::query()->forUser($user->id)->count())->toBe(0);
});

it('importa o historico local de forma idempotente e sem duplicar o lote', function () {
    $user = User::factory()->create();

    $payload = ['colors' => ['#AABBCCDD', '#11111111', '#aabbccdd']];

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.history.import'), $payload)
        ->assertOk()
        ->assertJsonPath('imported', 2)
        ->assertJsonPath('total', 3);

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.history.import'), $payload)
        ->assertOk()
        ->assertJsonPath('imported', 2)
        ->assertJsonPath('total', 3);

    expect(ColorHistoryEntry::query()->forUser($user->id)->orderBy('color')->pluck('color')->all())
        ->toBe(['#11111111', '#aabbccdd']);
});

it('recusa lote de importacao acima do limite e cor invalida', function () {
    config(['colors.library.import_batch' => 2]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.history.import'), [
            'colors' => ['#11111111', '#22222222', '#33333333'],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['errors' => ['colors']]]]);

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.history.import'), [
            'colors' => ['#11111111', '#fff'],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['errors' => ['colors.1']]]]);
});

it('favorita e desfavorita de forma idempotente, so na propria conta', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $first = $this->actingAs($user)
        ->postJson(route('api.tools.colors.favorites.store'), ['color' => '#AABBCCDD'])
        ->assertOk()
        ->assertJsonPath('favorited', true)
        ->assertJsonPath('entry.color', '#aabbccdd');

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.favorites.store'), ['color' => '#aabbccdd'])
        ->assertOk()
        ->assertJsonPath('entry.id', $first->json('entry.id'));

    ColorFavorite::create(['user_id' => $other->id, 'color' => '#33333333']);

    expect(ColorFavorite::query()->forUser($user->id)->count())->toBe(1);

    $this->actingAs($user)
        ->getJson(route('api.tools.colors.favorites.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $first->json('entry.id'));

    $this->actingAs($user)
        ->deleteJson(route('api.tools.colors.favorites.destroy'), ['color' => '#aabbccdd'])
        ->assertOk()
        ->assertJsonPath('favorited', false);

    $this->actingAs($user)
        ->deleteJson(route('api.tools.colors.favorites.destroy'), ['color' => '#aabbccdd'])
        ->assertOk()
        ->assertJsonPath('favorited', false);

    expect(ColorFavorite::query()->forUser($user->id)->count())->toBe(0)
        ->and(ColorFavorite::query()->forUser($other->id)->count())->toBe(1);
});

it('limpa todos os favoritos da conta sem tocar nas outras', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    ColorFavorite::create(['user_id' => $user->id, 'color' => '#11111111']);
    ColorFavorite::create(['user_id' => $user->id, 'color' => '#22222222']);
    ColorFavorite::create(['user_id' => $other->id, 'color' => '#33333333']);

    $this->actingAs($user)
        ->deleteJson(route('api.tools.colors.favorites.destroy'))
        ->assertOk()
        ->assertJsonPath('favorited', false);

    expect(ColorFavorite::query()->forUser($user->id)->count())->toBe(0)
        ->and(ColorFavorite::query()->forUser($other->id)->count())->toBe(1);
});

it('aplica o limiter de escrita por conta', function () {
    config(['colors.rates.library_per_minute' => 1]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.history.store'), ['color' => '#11111111'])
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.history.store'), ['color' => '#22222222'])
        ->assertTooManyRequests();
});
