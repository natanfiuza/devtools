<?php

use App\Models\ColorPalette;
use App\Models\User;
use App\Support\Colors\PaletteOgStorage;
use Database\Factories\ColorPaletteFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Pagina publica, duplicacao e OG por HTTP (F4, ADR-124/ADR-125).
|
| A visibilidade e o unico controle de acesso da pagina: privada, arquivada
| e slug inexistente respondem igual. A duplicacao exige conta e respeita a
| cota; o upload de OG so vale para o dono e o serving so para paleta
| publica ativa com arquivo presente.
*/

beforeEach(function () {
    config(['colors.og.path' => 'framework/testing/colors-og']);
});

afterEach(function () {
    File::deleteDirectory(storage_path('framework/testing/colors-og'));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function publicDuplicatePayload(array $overrides = []): array
{
    $colors = ColorPaletteFactory::scaleColors();

    return array_merge([
        'colors' => $colors,
        'roles' => ColorPaletteFactory::rolesFromScale($colors['items']),
        'tags' => [],
    ], $overrides);
}

it('mostra a paleta publica para visitante', function () {
    $palette = ColorPalette::factory()->public()->create([
        'name' => 'Compartilhada',
        'slug' => 'compartilhada',
    ]);

    $this->get(route('colors.palettes.show', ['locale' => 'en', 'slug' => 'compartilhada']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('colors/PublicPalette')
            ->where('palette.id', $palette->id)
            ->where('palette.name', 'Compartilhada')
            ->where('palette.slug', 'compartilhada')
            ->where('ogUrl', null)
            ->missing('palette.roles')
            ->missing('palette.source'));
});

it('responde 404 para privada, arquivada e slug inexistente', function () {
    ColorPalette::factory()->create(['slug' => 'privada']);
    ColorPalette::factory()->public()->archived()->create(['slug' => 'arquivada']);

    $this->get(route('colors.palettes.show', ['locale' => 'en', 'slug' => 'privada']))->assertNotFound();
    $this->get(route('colors.palettes.show', ['locale' => 'en', 'slug' => 'arquivada']))->assertNotFound();
    $this->get(route('colors.palettes.show', ['locale' => 'en', 'slug' => 'nao-existe']))->assertNotFound();
});

it('exige conta para duplicar e usa a intencao depois do login', function () {
    $palette = ColorPalette::factory()->public()->create(['slug' => 'origem']);

    $this->get(route('colors.palettes.duplicate.create', ['locale' => 'en', 'slug' => 'origem']))
        ->assertRedirect(route('login'));

    $this->post(route('colors.palettes.duplicate.store', ['locale' => 'en', 'slug' => 'origem']))
        ->assertRedirect(route('login'));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('colors.palettes.duplicate.create', ['locale' => 'en', 'slug' => 'origem']))
        ->assertRedirect(route('colors.palettes.show', ['locale' => 'en', 'slug' => 'origem', 'duplicate' => 1]));

    expect(ColorPalette::where('user_id', $user->id)->exists())->toBeFalse();
    expect($palette->fresh()->duplications_count)->toBe(0);
});

it('duplica sem override copiando o retrato da origem e contando uso de outro dono', function () {
    $owner = User::factory()->create();
    $source = ColorPalette::factory()->for($owner)->public()->create([
        'name' => 'Origem',
        'slug' => 'origem',
        'duplications_count' => 0,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('colors.palettes.duplicate.store', ['locale' => 'en', 'slug' => 'origem']))
        ->assertRedirect();

    $copy = ColorPalette::where('user_id', $user->id)->sole();

    expect($copy->name)->toBe('Origem'.__('colors.palette.copy_suffix'))
        ->and($copy->visibility->value)->toBe('private')
        ->and($copy->slug)->toBeNull()
        ->and($copy->duplicated_from_id)->toBe($source->id)
        ->and($copy->colors)->toBe($source->colors)
        ->and($copy->roles)->toBe($source->roles)
        ->and($copy->og_version)->toBeNull();

    expect($source->fresh()->duplications_count)->toBe(1);
});

it('duplica com nova base enviada pelo navegador sem tocar na origem', function () {
    $owner = User::factory()->create();
    $source = ColorPalette::factory()->for($owner)->public()->create(['slug' => 'origem']);

    $user = User::factory()->create();
    $payload = publicDuplicatePayload();
    $payload['colors']['base'] = '#ffffff';

    $this->actingAs($user)
        ->post(route('colors.palettes.duplicate.store', ['locale' => 'en', 'slug' => 'origem']), $payload)
        ->assertRedirect();

    $copy = ColorPalette::where('user_id', $user->id)->sole();

    expect($copy->kind->value)->toBe('scale')
        ->and($copy->colors['base'])->toBe('#ffffff')
        ->and($copy->source)->toBeNull()
        ->and($copy->tags()->count())->toBe(0);

    expect($source->fresh()->colors['base'])->toBe('#6366f1');
});

it('duplica a propria paleta pelo dashboard sem contar uso', function () {
    $user = User::factory()->create();
    $palette = ColorPalette::factory()->for($user)->create(['name' => 'Minha']);

    $this->actingAs($user)
        ->post(route('dashboard.colors.palettes.duplicate', $palette))
        ->assertRedirect();

    expect(ColorPalette::where('user_id', $user->id)->count())->toBe(2)
        ->and($palette->fresh()->duplications_count)->toBe(0);
});

it('respeita a cota ao duplicar', function () {
    config(['colors.palettes.free_limit' => 1]);

    $owner = User::factory()->create();
    ColorPalette::factory()->for($owner)->public()->create(['slug' => 'origem']);

    $user = User::factory()->create();
    ColorPalette::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('colors.palettes.duplicate.store', ['locale' => 'en', 'slug' => 'origem']))
        ->assertSessionHasErrors('palette');

    expect(ColorPalette::where('user_id', $user->id)->count())->toBe(1);
});

it('aceita upload de PNG 1200x630 do dono e serve a paleta publica', function () {
    $user = User::factory()->create();
    $palette = ColorPalette::factory()->for($user)->public()->create(['slug' => 'com-og']);

    $file = UploadedFile::fake()->image('og.png', 1200, 630);

    $this->actingAs($user)
        ->post(route('dashboard.colors.palettes.og.store', $palette), ['image' => $file])
        ->assertRedirect();

    expect($palette->fresh()->og_version)->toBe(1)
        ->and((new PaletteOgStorage)->exists($palette->id))->toBeTrue();

    $response = $this->get(route('colors.palettes.og', ['locale' => 'en', 'slug' => 'com-og', 'v' => 1]));
    $response->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($response->headers->get('Cache-Control'))->toContain('max-age=');

    $this->get(route('colors.palettes.show', ['locale' => 'en', 'slug' => 'com-og']))
        ->assertInertia(fn (Assert $page) => $page->where('ogUrl', route('colors.palettes.og', [
            'locale' => 'en',
            'slug' => 'com-og',
            'v' => 1,
        ])));
});

it('recusa imagem fora de dimensao ou tipo e nao deixa outra conta enviar', function () {
    $user = User::factory()->create();
    $palette = ColorPalette::factory()->for($user)->public()->create(['slug' => 'com-og']);

    $this->actingAs($user)
        ->post(route('dashboard.colors.palettes.og.store', $palette), [
            'image' => UploadedFile::fake()->image('og.png', 100, 100),
        ])
        ->assertSessionHasErrors('image');

    $this->actingAs($user)
        ->post(route('dashboard.colors.palettes.og.store', $palette), [
            'image' => UploadedFile::fake()->create('arquivo.txt', 10, 'text/plain'),
        ])
        ->assertSessionHasErrors('image');

    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->post(route('dashboard.colors.palettes.og.store', $palette), [
            'image' => UploadedFile::fake()->image('og.png', 1200, 630),
        ])
        ->assertNotFound();

    expect($palette->fresh()->og_version)->toBeNull();
});

it('nao serve OG de paleta privada, arquivada ou sem arquivo', function () {
    $storage = new PaletteOgStorage;

    $private = ColorPalette::factory()->create(['slug' => 'privada', 'og_version' => 1]);
    $storage->put($private->id, 'png');

    $archived = ColorPalette::factory()->public()->archived()->create(['slug' => 'arquivada', 'og_version' => 1]);
    $storage->put($archived->id, 'png');

    ColorPalette::factory()->public()->create(['slug' => 'sem-og']);

    $this->get(route('colors.palettes.og', ['locale' => 'en', 'slug' => 'privada']))->assertNotFound();
    $this->get(route('colors.palettes.og', ['locale' => 'en', 'slug' => 'arquivada']))->assertNotFound();
    $this->get(route('colors.palettes.og', ['locale' => 'en', 'slug' => 'sem-og']))->assertNotFound();
});

it('remove a OG do dono e zera a versao', function () {
    $user = User::factory()->create();
    $palette = ColorPalette::factory()->for($user)->public()->create(['slug' => 'com-og', 'og_version' => 3]);

    $storage = new PaletteOgStorage;
    $storage->put($palette->id, 'png');

    $this->actingAs($user)
        ->delete(route('dashboard.colors.palettes.og.destroy', $palette))
        ->assertRedirect();

    expect($palette->fresh()->og_version)->toBeNull()
        ->and($storage->exists($palette->id))->toBeFalse();
});
