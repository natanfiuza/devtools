<?php

use App\Enums\AdminActionType;
use App\Enums\UserRole;
use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('faz a conta nascer como usuario comum', function () {
    expect(User::factory()->create()->role)->toBe(UserRole::User);
});

it('nao aceita papel vindo do request', function () {
    // `role` esta fora do #[Fillable]: o que vier do formulario e descartado.
    $user = User::create([
        'name' => 'Fulano',
        'email' => 'fulano@example.com',
        'password' => 'senha-bem-longa-123',
        'role' => UserRole::Admin->value,
    ]);

    expect($user->isAdmin())->toBeFalse();
});

it('reconhece quem e admin', function () {
    expect(User::factory()->admin()->create()->isAdmin())->toBeTrue();
    expect(User::factory()->create()->isAdmin())->toBeFalse();
});

it('abre o painel so para admin', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    expect(Gate::forUser($admin)->allows('access-admin'))->toBeTrue();
    expect(Gate::forUser($user)->allows('access-admin'))->toBeFalse();
});

it('protege as acoes destrutivas sobre admin e sobre a propria conta', function () {
    $admin = User::factory()->admin()->create();
    $outroAdmin = User::factory()->admin()->create();
    $user = User::factory()->create();

    expect(Gate::forUser($admin)->allows('manage', $user))->toBeTrue();

    // Suspender, excluir ou entrar como: nunca em admin, nunca em si mesmo.
    expect(Gate::forUser($admin)->allows('manage', $outroAdmin))->toBeFalse();
    expect(Gate::forUser($admin)->allows('manage', $admin))->toBeFalse();
    expect(Gate::forUser($user)->allows('manage', $user))->toBeFalse();
});

it('conta ao frontend se quem esta logado e admin', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isAdmin', true));

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isAdmin', false));
});

it('nao expoe o papel no payload do usuario', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->missing('auth.user.role'));
});

it('promove a conta pelo comando', function () {
    $user = User::factory()->create(['email' => 'admin@exemplo.com']);

    $this->artisan('devtools:admin', ['email' => 'admin@exemplo.com'])->assertSuccessful();

    expect($user->refresh()->isAdmin())->toBeTrue();

    $registro = AdminAction::query()->sole();

    expect($registro->action)->toBe(AdminActionType::UserRoleGranted)
        ->and($registro->target_id)->toBe($user->id)
        ->and($registro->actor_id)->toBeNull()
        ->and($registro->meta)->toBe(['via' => 'cli', 'para' => 'admin']);
});

it('rebaixa a conta com --revoke', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@exemplo.com']);

    $this->artisan('devtools:admin', ['email' => 'admin@exemplo.com', '--revoke' => true])
        ->assertSuccessful();

    expect($admin->refresh()->isAdmin())->toBeFalse()
        ->and(AdminAction::query()->sole()->action)->toBe(AdminActionType::UserRoleRevoked);
});

it('nao muda nada quando o papel ja e o pedido', function () {
    User::factory()->admin()->create(['email' => 'admin@exemplo.com']);

    $this->artisan('devtools:admin', ['email' => 'admin@exemplo.com'])->assertSuccessful();

    expect(AdminAction::query()->count())->toBe(0);
});

it('falha com e-mail que nao existe', function () {
    $this->artisan('devtools:admin', ['email' => 'ninguem@exemplo.com'])->assertFailed();

    expect(AdminAction::query()->count())->toBe(0);
});
