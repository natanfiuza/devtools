<?php

use App\Enums\AdminActionType;
use App\Models\AdminAction;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| "Entrar como" (ADR-025).
|
| A sessao passa a ser a do alvo e o caminho de volta fica numa chave de
| sessao. O painel fica inacessivel enquanto isso - o alvo nao e admin -, e a
| unica porta de volta e a rota `impersonation.destroy`.
|
| `actingAs` fixa o usuario no guard e sobrevive as requisicoes seguintes do
| mesmo teste. Para ver a sessao de verdade (que e onde a impersonation vive),
| o guard e esquecido antes da requisicao seguinte: e o `forgetGuards` abaixo.
*/

it('entra como a conta do alvo e registra a auditoria', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.impersonate', $user))
        ->assertRedirect(route('dashboard'));

    $registro = AdminAction::query()->sole();

    expect($registro->action)->toBe(AdminActionType::UserImpersonated)
        ->and($registro->actor_id)->toBe($admin->id)
        ->and($registro->target_id)->toBe($user->id);

    $this->app['auth']->forgetGuards();

    // A sessao agora e a do alvo, e o site conta de quem ela veio.
    expect(Auth::id())->toBe($user->id);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('impersonation.email', $admin->email)
            ->where('auth.user.id', $user->id));
});

it('fecha o painel enquanto se esta como outro', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.users.impersonate', $user));
    $this->app['auth']->forgetGuards();

    // O alvo nao e admin: a porta do painel fecha sozinha, sem regra nova.
    $this->get(route('admin.dashboard'))->assertForbidden();
    $this->get(route('admin.users.index'))->assertForbidden();

    // A area do alvo continua aberta: o que muda e so quem esta agindo.
    $this->get(route('profile.edit'))->assertOk();
});

it('volta para a conta de origem', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.users.impersonate', $user));
    $this->app['auth']->forgetGuards();

    $this->delete(route('impersonation.destroy'))
        ->assertRedirect(route('admin.users.index'));

    $this->app['auth']->forgetGuards();

    expect(Auth::id())->toBe($admin->id);

    $this->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('impersonation', null));
});

it('nao faz nada quando nao ha impersonation em curso', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('impersonation.destroy'))
        ->assertRedirect(route('dashboard'));

    expect(Auth::id())->toBe($user->id);
});

it('recusa entrar como outro admin, como a propria conta e como conta suspensa', function () {
    $admin = User::factory()->admin()->create();
    $outroAdmin = User::factory()->admin()->create();
    $suspenso = User::factory()->create(['suspended_at' => now()]);

    $this->actingAs($admin)
        ->post(route('admin.users.impersonate', $outroAdmin))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('admin.users.impersonate', $admin))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('admin.users.impersonate', $suspenso))
        ->assertForbidden();

    // Nenhuma das tentativas chegou a marcar a sessao.
    expect(session(Impersonation::SESSION_KEY))->toBeNull()
        ->and(AdminAction::query()->count())->toBe(0);
});

it('nao abre uma impersonation sobre outra', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    // Encadear apagaria o caminho de volta: quem ja esta como outro nao abre
    // uma segunda impersonation.
    $this->actingAs($admin)
        ->withSession([Impersonation::SESSION_KEY => $admin->id])
        ->post(route('admin.users.impersonate', $user))
        ->assertForbidden();

    expect(AdminAction::query()->count())->toBe(0);
});

it('sai da conta quando o admin de origem deixa de existir', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.users.impersonate', $user));
    $this->app['auth']->forgetGuards();

    // Admin excluido no meio do caminho: a chave sai da sessao e o site volta
    // a ser so a conta do alvo.
    $admin->forceFill(['anonymized_at' => now()])->save();
    $admin->delete();

    expect(Impersonation::actor(request()))->toBeNull();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('impersonation', null));
});
