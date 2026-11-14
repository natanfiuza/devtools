<?php

use App\Models\ColorPalette;
use App\Models\ColorPreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Presets do gerador (F5, ADR-093).
|
| Um preset guarda nome + base, nunca a escala; aplicar e do navegador. A
| gestao e por conta: nome unico, base imutavel, recurso alheio em 404 e
| nenhuma relacao com a cota ou com a lista de paletas.
*/

it('redireciona visitante ao login em todas as rotas de preset', function () {
    $preset = ColorPreset::factory()->create();

    $this->getJson(route('api.tools.colors.presets.index'))->assertUnauthorized();
    $this->postJson(route('api.tools.colors.presets.store'), [
        'name' => 'Marca',
        'base' => '#3366ff',
    ])->assertUnauthorized();
    $this->patchJson(route('api.tools.colors.presets.update', $preset->id), [
        'name' => 'Outro',
    ])->assertUnauthorized();
    $this->deleteJson(route('api.tools.colors.presets.destroy', $preset->id))
        ->assertUnauthorized();
});

it('cria o preset com a base normalizada e sem virar paleta', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.presets.store'), [
            'name' => '  Marca  ',
            'base' => '  #3366FF  ',
        ])
        ->assertCreated()
        ->assertJsonPath('preset.name', 'Marca')
        ->assertJsonPath('preset.base', '#3366ff')
        ->assertJsonStructure([
            'preset' => ['id', 'name', 'base', 'created_at', 'updated_at'],
        ]);

    expect(ColorPreset::query()->forUser($user->id)->count())->toBe(1)
        ->and(ColorPalette::query()->count())->toBe(0);
});

it('aceita base com alfa em HEX8', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.presets.store'), [
            'name' => 'Vidro',
            'base' => '#3366FF80',
        ])
        ->assertCreated()
        ->assertJsonPath('preset.base', '#3366ff80');
});

it('recusa nome vazio, nome longo e base fora do HEX', function () {
    config(['colors.presets.name_max_length' => 5]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.presets.store'), [
            'name' => '   ',
            'base' => '#3366ff',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['errors' => ['name']]]]);

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.presets.store'), [
            'name' => 'Demasiado',
            'base' => '#3366ff',
        ])
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['details' => ['errors' => ['name']]]]);

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.presets.store'), [
            'name' => 'Marca',
            'base' => '#fff',
        ])
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['details' => ['errors' => ['base']]]]);

    expect(ColorPreset::query()->count())->toBe(0);
});

it('mantem o nome unico por conta e permite repetir em outra conta', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    ColorPreset::factory()->for($user)->create(['name' => 'Marca']);

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.presets.store'), [
            'name' => 'Marca',
            'base' => '#3366ff',
        ])
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['details' => ['errors' => ['name']]]]);

    $this->actingAs($other)
        ->postJson(route('api.tools.colors.presets.store'), [
            'name' => 'Marca',
            'base' => '#3366ff',
        ])
        ->assertCreated();
});

it('lista apenas os presets da conta, do mais recente para o mais antigo', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $older = ColorPreset::factory()->for($user)->create(['name' => 'Antigo']);
    $newer = ColorPreset::factory()->for($user)->create(['name' => 'Novo']);
    ColorPreset::query()->whereKey($older->id)->update(['updated_at' => now()->subMinute()]);
    ColorPreset::factory()->for($other)->create(['name' => 'Alheio']);

    $this->actingAs($user)
        ->getJson(route('api.tools.colors.presets.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('limits.limit', null)
        ->assertJsonPath('limits.used', 2)
        ->assertJsonPath('form.name_max_length', 60);
});

it('publica o teto configurado em limits e form', function () {
    config([
        'colors.presets.limit' => 3,
        'colors.presets.name_max_length' => 42,
    ]);

    $user = User::factory()->create();
    ColorPreset::factory()->for($user)->create();

    $this->actingAs($user)
        ->getJson(route('api.tools.colors.presets.index'))
        ->assertOk()
        ->assertJsonPath('limits.limit', 3)
        ->assertJsonPath('limits.used', 1)
        ->assertJsonPath('form.name_max_length', 42);
});

it('renomeia o proprio preset sem alterar a base', function () {
    $user = User::factory()->create();
    $preset = ColorPreset::factory()->for($user)->create([
        'name' => 'Marca',
        'base' => '#3366ff',
    ]);

    $this->travel(2)->seconds();

    $this->actingAs($user)
        ->patchJson(route('api.tools.colors.presets.update', $preset->id), [
            'name' => '  Nova marca  ',
            'base' => '#000000',
        ])
        ->assertOk()
        ->assertJsonPath('preset.name', 'Nova marca')
        ->assertJsonPath('preset.base', '#3366ff');

    $preset->refresh();

    expect($preset->name)->toBe('Nova marca')
        ->and($preset->base)->toBe('#3366ff')
        ->and($preset->updated_at?->greaterThan($preset->created_at))->toBeTrue();
});

it('recusa renomear para um nome que a conta ja usa', function () {
    $user = User::factory()->create();

    ColorPreset::factory()->for($user)->create(['name' => 'Marca']);
    $second = ColorPreset::factory()->for($user)->create(['name' => 'Outro']);

    $this->actingAs($user)
        ->patchJson(route('api.tools.colors.presets.update', $second->id), [
            'name' => 'Marca',
        ])
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['details' => ['errors' => ['name']]]]);

    $this->actingAs($user)
        ->patchJson(route('api.tools.colors.presets.update', $second->id), [
            'name' => 'Outro',
        ])
        ->assertOk();
});

it('responde 404 para preset de outra conta ou inexistente', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $preset = ColorPreset::factory()->for($other)->create();

    $this->actingAs($user)
        ->patchJson(route('api.tools.colors.presets.update', $preset->id), [
            'name' => 'Invasao',
        ])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    $this->actingAs($user)
        ->deleteJson(route('api.tools.colors.presets.destroy', $preset->id))
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    $this->actingAs($user)
        ->deleteJson(route('api.tools.colors.presets.destroy', '01JZZZZZZZZZZZZZZZZZZZZZZZ'))
        ->assertNotFound();

    expect(ColorPreset::query()->whereKey($preset->id)->exists())->toBeTrue();
});

it('exclui o proprio preset', function () {
    $user = User::factory()->create();
    $preset = ColorPreset::factory()->for($user)->create();

    $this->actingAs($user)
        ->deleteJson(route('api.tools.colors.presets.destroy', $preset->id))
        ->assertOk()
        ->assertJsonPath('deleted', true);

    expect(ColorPreset::query()->whereKey($preset->id)->exists())->toBeFalse();
});

it('respeita o teto configurado e libera a vaga ao excluir', function () {
    config(['colors.presets.limit' => 1]);

    $user = User::factory()->create();
    $preset = ColorPreset::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.presets.store'), [
            'name' => 'Segundo',
            'base' => '#3366ff',
        ])
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['details' => ['errors' => ['preset']]]]);

    $this->actingAs($user)
        ->deleteJson(route('api.tools.colors.presets.destroy', $preset->id))
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.presets.store'), [
            'name' => 'Segundo',
            'base' => '#3366ff',
        ])
        ->assertCreated();
});

it('aplica o limiter de escrita por conta', function () {
    config(['colors.rates.library_per_minute' => 1]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.presets.store'), [
            'name' => 'Marca',
            'base' => '#3366ff',
        ])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.presets.store'), [
            'name' => 'Outra',
            'base' => '#22aa66',
        ])
        ->assertTooManyRequests()
        ->assertJsonStructure(['message']);
});
