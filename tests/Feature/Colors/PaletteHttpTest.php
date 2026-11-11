<?php

use App\Models\ColorPalette;
use App\Models\ColorTag;
use App\Models\User;
use Database\Factories\ColorPaletteFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Camada HTTP de Minhas Paletas (F4, ADR-123/ADR-103/ADR-109).
|
| Cobre autenticacao, ownership (404), validacao do payload por tipo, cota,
| busca/filtro/ordenacao no servidor, arquivamento, exclusao confirmada e
| tags como entidade da conta.
*/

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function httpScalePayload(array $overrides = []): array
{
    $colors = ColorPaletteFactory::scaleColors();

    return array_merge([
        'name' => 'Paleta HTTP',
        'description' => 'Descricao da paleta',
        'visibility' => 'private',
        'kind' => 'scale',
        'algorithm_version' => 1,
        'colors' => $colors,
        'roles' => ColorPaletteFactory::rolesFromScale($colors['items']),
        'source' => null,
        'tags' => [],
    ], $overrides);
}

it('redireciona visitante ao login', function () {
    $this->get(route('dashboard.colors.palettes.index'))->assertRedirect(route('login'));
    $this->get(route('dashboard.colors.palettes.create'))->assertRedirect(route('login'));
});

it('lista apenas as paletas da propria conta, com cota e filtros', function () {
    $user = User::factory()->create();
    $mine = ColorPalette::factory()->for($user)->create(['name' => 'Minha Paleta']);
    ColorPalette::factory()->create(['name' => 'De Outro']);

    $this->actingAs($user)
        ->get(route('dashboard.colors.palettes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/colors/PalettesIndex')
            ->has('palettes.data', 1)
            ->where('palettes.data.0.id', $mine->id)
            ->where('palettes.data.0.name', 'Minha Paleta')
            ->where('filters.status', 'active')
            ->where('limits.limit', 25)
            ->where('limits.used', 1)
            ->where('limits.remaining', 24)
            ->where('form.nameMaxLength', 80)
            ->where('form.descriptionMaxLength', 500)
            ->where('form.tagsPerPalette', 10)
            ->where('form.tagNameMaxLength', 30));
});

it('entrega os limites do editor junto com os da importacao de imagem', function () {
    $user = User::factory()->create();
    $palette = ColorPalette::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard.colors.palettes.edit', $palette))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/colors/PaletteEditor')
            ->where('palette.id', $palette->id)
            ->where('form.nameMaxLength', 80)
            ->where('form.tagsPerPalette', 10)
            ->where('image.maxBytes', 10 * 1024 * 1024)
            ->where('image.analysisSide', 512));
});

it('busca no servidor por nome, descricao e tag', function () {
    $user = User::factory()->create();
    $tag = ColorTag::factory()->for($user)->create(['name' => 'Brand']);

    ColorPalette::factory()->for($user)->create(['name' => 'Oceano', 'description' => 'azul calmo']);
    ColorPalette::factory()->for($user)->create(['name' => 'Floresta', 'description' => 'verde']);
    $tagged = ColorPalette::factory()->for($user)->create(['name' => 'Sunset', 'description' => null]);
    $tagged->tags()->attach($tag);

    $this->actingAs($user)
        ->get(route('dashboard.colors.palettes.index', ['q' => 'oceano']))
        ->assertInertia(fn (Assert $page) => $page->has('palettes.data', 1)->where('palettes.data.0.name', 'Oceano'));

    $this->actingAs($user)
        ->get(route('dashboard.colors.palettes.index', ['q' => 'verde']))
        ->assertInertia(fn (Assert $page) => $page->has('palettes.data', 1)->where('palettes.data.0.name', 'Floresta'));

    $this->actingAs($user)
        ->get(route('dashboard.colors.palettes.index', ['q' => 'brand']))
        ->assertInertia(fn (Assert $page) => $page->has('palettes.data', 1)->where('palettes.data.0.name', 'Sunset'));
});

it('filtra por tag, por status e ordena por nome', function () {
    $user = User::factory()->create();
    $tag = ColorTag::factory()->for($user)->create(['name' => 'Web']);

    $beta = ColorPalette::factory()->for($user)->create(['name' => 'Beta']);
    ColorPalette::factory()->for($user)->create(['name' => 'Alpha']);
    ColorPalette::factory()->for($user)->archived()->create(['name' => 'Antiga']);
    $beta->tags()->attach($tag);

    $this->actingAs($user)
        ->get(route('dashboard.colors.palettes.index', ['tag' => $tag->id]))
        ->assertInertia(fn (Assert $page) => $page->has('palettes.data', 1)->where('palettes.data.0.name', 'Beta'));

    $this->actingAs($user)
        ->get(route('dashboard.colors.palettes.index', ['status' => 'archived']))
        ->assertInertia(fn (Assert $page) => $page->has('palettes.data', 1)->where('palettes.data.0.name', 'Antiga'));

    $this->actingAs($user)
        ->get(route('dashboard.colors.palettes.index', ['sort' => 'name', 'direction' => 'asc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('palettes.data.0.name', 'Alpha')
            ->where('palettes.data.1.name', 'Beta'));
});

it('ignora ordenacao e status fora da whitelist', function () {
    $user = User::factory()->create();
    ColorPalette::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard.colors.palettes.index', ['sort' => 'vote_count', 'direction' => 'sideways', 'status' => 'deleted']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'updated_at')
            ->where('filters.direction', 'desc')
            ->where('filters.status', 'active'));
});

it('pagina o acervo e mantem a query string', function () {
    config(['colors.palettes.per_page' => 2]);

    $user = User::factory()->create();
    ColorPalette::factory()->count(5)->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard.colors.palettes.index', ['page' => 2, 'sort' => 'name']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('palettes.data', 2)
            ->where('palettes.total', 5)
            ->where('palettes.current_page', 2));
});

it('cria a paleta com cores, papeis e tags persistidos', function () {
    $user = User::factory()->create();
    $payload = httpScalePayload(['tags' => ['Design', 'web']]);

    $response = $this->actingAs($user)->post(route('dashboard.colors.palettes.store'), $payload);

    $palette = ColorPalette::sole();

    $response->assertRedirect(route('dashboard.colors.palettes.edit', $palette));

    expect($palette->user_id)->toBe($user->id)
        ->and($palette->name)->toBe('Paleta HTTP')
        ->and($palette->colors)->toBe($payload['colors'])
        ->and($palette->roles)->toBe($payload['roles'])
        ->and($palette->colors_count)->toBe(11)
        ->and($palette->tags()->count())->toBe(2)
        ->and($palette->slug)->toBeNull();
});

it('cria paleta extraida com origem sem imagem e recusa contagem invalida', function () {
    $user = User::factory()->create();

    $items = [
        ['hex' => '#112233'],
        ['hex' => '#334455'],
        ['hex' => '#556677'],
        ['hex' => '#778899'],
        ['hex' => '#99aabb'],
    ];

    $payload = httpScalePayload([
        'kind' => 'extracted',
        'colors' => ['base' => '#334455', 'items' => $items],
        'roles' => ColorPaletteFactory::rolesFromExtracted(),
        'source' => ['kind' => 'file', 'label' => 'foto.png'],
    ]);

    $this->actingAs($user)->post(route('dashboard.colors.palettes.store'), $payload)->assertRedirect();

    $palette = ColorPalette::sole();
    expect($palette->kind->value)->toBe('extracted')
        ->and($palette->source)->toBe(['kind' => 'file', 'label' => 'foto.png'])
        ->and($palette->colors_count)->toBe(5);

    $invalid = $payload;
    $invalid['colors']['items'] = array_slice($items, 0, 4);

    $this->actingAs($user)
        ->post(route('dashboard.colors.palettes.store'), $invalid)
        ->assertSessionHasErrors('colors.items');
});

it('recusa base fora das cores extraidas, versao desconhecida e origem na escala', function () {
    $user = User::factory()->create();

    $items = [
        ['hex' => '#112233'],
        ['hex' => '#334455'],
        ['hex' => '#556677'],
        ['hex' => '#778899'],
        ['hex' => '#99aabb'],
    ];

    $this->actingAs($user)
        ->post(route('dashboard.colors.palettes.store'), httpScalePayload([
            'kind' => 'extracted',
            'colors' => ['base' => '#ffffff', 'items' => $items],
            'roles' => ColorPaletteFactory::rolesFromExtracted(),
        ]))
        ->assertSessionHasErrors('colors.base');

    $this->actingAs($user)
        ->post(route('dashboard.colors.palettes.store'), httpScalePayload(['algorithm_version' => 99]))
        ->assertSessionHasErrors('algorithm_version');

    $this->actingAs($user)
        ->post(route('dashboard.colors.palettes.store'), httpScalePayload([
            'source' => ['kind' => 'file', 'label' => 'foto.png'],
        ]))
        ->assertSessionHasErrors('source');

    expect(ColorPalette::count())->toBe(0);
});

it('aplica a cota Free no servidor', function () {
    config(['colors.palettes.free_limit' => 1]);

    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.colors.palettes.store'), httpScalePayload())->assertRedirect();

    $this->actingAs($user)
        ->post(route('dashboard.colors.palettes.store'), httpScalePayload(['name' => 'Outra']))
        ->assertSessionHasErrors('palette');

    expect(ColorPalette::count())->toBe(1);
});

it('edita, arquiva e restaura a propria paleta', function () {
    $user = User::factory()->create();
    $palette = ColorPalette::factory()->for($user)->public()->create([
        'name' => 'Original',
        'slug' => 'original',
    ]);

    $this->actingAs($user)
        ->patch(route('dashboard.colors.palettes.update', $palette), httpScalePayload(['name' => 'Renomeada', 'visibility' => 'public']))
        ->assertRedirect();

    expect($palette->fresh()->name)->toBe('Renomeada')
        ->and($palette->fresh()->slug)->toBe('original');

    $this->actingAs($user)
        ->patch(route('dashboard.colors.palettes.archive', $palette))
        ->assertRedirect();

    $archived = $palette->fresh();
    expect($archived->archived_at)->not->toBeNull()
        ->and($archived->slug)->toBeNull()
        ->and($archived->archived_slug)->toBe('original');

    $this->actingAs($user)
        ->patch(route('dashboard.colors.palettes.restore', $palette))
        ->assertRedirect();

    expect($palette->fresh()->archived_at)->toBeNull()
        ->and($palette->fresh()->slug)->toBe('original');
});

it('nao permite editar paleta arquivada', function () {
    $user = User::factory()->create();
    $palette = ColorPalette::factory()->for($user)->archived()->create();

    $this->actingAs($user)
        ->patch(route('dashboard.colors.palettes.update', $palette), httpScalePayload())
        ->assertSessionHasErrors('palette');

    expect($palette->fresh()->name)->not->toBe('Paleta HTTP');
});

it('exige o nome exato para excluir e libera a linha depois', function () {
    $user = User::factory()->create();
    $palette = ColorPalette::factory()->for($user)->create(['name' => 'Exata']);

    $this->actingAs($user)
        ->delete(route('dashboard.colors.palettes.destroy', $palette), ['confirm_name' => 'Errada'])
        ->assertSessionHasErrors('confirm_name');

    expect(ColorPalette::whereKey($palette->id)->exists())->toBeTrue();

    $this->actingAs($user)
        ->delete(route('dashboard.colors.palettes.destroy', $palette), ['confirm_name' => 'Exata'])
        ->assertRedirect(route('dashboard.colors.palettes.index'));

    expect(ColorPalette::whereKey($palette->id)->exists())->toBeFalse();
});

it('responde 404 para paleta de outra conta em todas as acoes', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $palette = ColorPalette::factory()->for($owner)->create(['name' => 'Privada']);

    $this->actingAs($intruder)->get(route('dashboard.colors.palettes.edit', $palette))->assertNotFound();
    $this->actingAs($intruder)->patch(route('dashboard.colors.palettes.update', $palette), httpScalePayload())->assertNotFound();
    $this->actingAs($intruder)->patch(route('dashboard.colors.palettes.archive', $palette))->assertNotFound();
    $this->actingAs($intruder)->patch(route('dashboard.colors.palettes.restore', $palette))->assertNotFound();
    $this->actingAs($intruder)->post(route('dashboard.colors.palettes.duplicate', $palette))->assertNotFound();
    $this->actingAs($intruder)->delete(route('dashboard.colors.palettes.destroy', $palette), ['confirm_name' => 'Privada'])->assertNotFound();

    expect(ColorPalette::whereKey($palette->id)->exists())->toBeTrue();
});

it('gerencia tags: cria, recusa duplicata, renomeia, reordena e exclui', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.colors.tags.store'), ['name' => 'Marca'])
        ->assertRedirect();

    $tag = ColorTag::query()->sole();
    expect($tag->name)->toBe('Marca')
        ->and($tag->position)->toBe(1);

    $this->actingAs($user)
        ->post(route('dashboard.colors.tags.store'), ['name' => '  marca '])
        ->assertSessionHasErrors('name');

    $second = ColorTag::factory()->for($user)->create(['name' => 'Web', 'key' => 'web', 'position' => 2]);

    $this->actingAs($user)
        ->patch(route('dashboard.colors.tags.reorder'), ['ids' => [$second->id, $tag->id]])
        ->assertRedirect();

    expect($second->fresh()->position)->toBe(0)
        ->and($tag->fresh()->position)->toBe(1);

    $this->actingAs($user)
        ->patch(route('dashboard.colors.tags.update', $tag), ['name' => 'Web'])
        ->assertSessionHasErrors('name');

    $this->actingAs($user)
        ->patch(route('dashboard.colors.tags.update', $tag), ['name' => 'Identidade'])
        ->assertRedirect();

    expect($tag->fresh()->name)->toBe('Identidade');

    $palette = ColorPalette::factory()->for($user)->create();
    $palette->tags()->attach($tag);

    $this->actingAs($user)
        ->delete(route('dashboard.colors.tags.destroy', $tag))
        ->assertRedirect();

    expect(ColorTag::whereKey($tag->id)->exists())->toBeFalse()
        ->and($palette->fresh()->tags()->count())->toBe(0);
});

it('nao deixa mexer em tag de outra conta e recusa reordenar id alheio', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $tag = ColorTag::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->patch(route('dashboard.colors.tags.update', $tag), ['name' => 'Minha'])
        ->assertNotFound();

    $this->actingAs($intruder)
        ->delete(route('dashboard.colors.tags.destroy', $tag))
        ->assertNotFound();

    $this->actingAs($intruder)
        ->patch(route('dashboard.colors.tags.reorder'), ['ids' => [$tag->id]])
        ->assertNotFound();
});
