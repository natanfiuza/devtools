<?php

use App\Enums\AdminActionType;
use App\Models\AdminAction;
use App\Models\OutputTarget;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Alvos de saida (F7): a tela e catalogo de leitura, exceto pelo estado
| ligado/desligado. O catalogo real nasce na migracao de seed, entao os testes
| contam a partir do que ja existe em vez de fixar nove.
*/

it('fecha a tela de alvos para visitante', function () {
    $this->get(route('admin.output-targets.index'))
        ->assertRedirect(route('login'));
});

it('fecha a tela de alvos para usuario comum', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.output-targets.index'))
        ->assertForbidden();
});

it('lista o catalogo com idioma, dialetos e plano exigido', function () {
    $admin = User::factory()->admin()->create();
    $plan = Plan::factory()->create(['name' => 'Plano Pro']);
    $target = OutputTarget::factory()->create([
        'slug' => 'alvo-teste',
        'label' => 'Alvo de Teste',
        'language' => 'sql',
        'supported_dialects' => ['mysql', 'sqlite'],
        'enabled' => false,
        'plan_required' => $plan->id,
        // A factory nasce com sort 0 e vem antes do catalogo semeado.
        'sort' => 0,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.output-targets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/OutputTargets')
            ->has('targets', OutputTarget::query()->count())
            ->where('targets.0.id', $target->id)
            ->where('targets.0.slug', 'alvo-teste')
            ->where('targets.0.label', 'Alvo de Teste')
            ->where('targets.0.language', 'sql')
            ->where('targets.0.dialects', ['mysql', 'sqlite'])
            ->where('targets.0.plan', 'Plano Pro')
            ->where('targets.0.enabled', false));
});

it('desliga um alvo e registra a auditoria', function () {
    $admin = User::factory()->admin()->create();
    $target = OutputTarget::factory()->create(['enabled' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.output-targets.update', $target), [
            'enabled' => false,
        ])
        ->assertRedirect(route('admin.output-targets.index'));

    expect($target->refresh()->enabled)->toBeFalse();

    $registro = AdminAction::query()->sole();

    expect($registro->action)->toBe(AdminActionType::OutputTargetUpdated)
        ->and($registro->target_type)->toBe(OutputTarget::class)
        ->and($registro->target_id)->toBe($target->id)
        ->and($registro->meta)->toMatchArray([
            'slug' => $target->slug,
            'enabled' => false,
        ]);
});

it('nao registra auditoria quando o estado ja e o pedido', function () {
    $admin = User::factory()->admin()->create();
    $target = OutputTarget::factory()->create(['enabled' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.output-targets.update', $target), [
            'enabled' => true,
        ])
        ->assertRedirect(route('admin.output-targets.index'));

    expect($target->refresh()->enabled)->toBeTrue()
        ->and(AdminAction::query()->count())->toBe(0);
});

it('exige um booleano no estado do alvo', function () {
    $admin = User::factory()->admin()->create();
    $target = OutputTarget::factory()->create(['enabled' => true]);

    $this->actingAs($admin)
        ->from(route('admin.output-targets.index'))
        ->patch(route('admin.output-targets.update', $target), [
            'enabled' => 'talvez',
        ])
        ->assertSessionHasErrors('enabled');

    expect($target->refresh()->enabled)->toBeTrue();
});
