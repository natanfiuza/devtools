<?php

use App\Models\AiCache;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Cache compartilhado da F7 (ADR-140).
|
| A coluna `purpose` separa o SQL builder da moderacao. A poda comum remove so
| linhas com data de expiracao; `--purpose --all` e o expurgo explicito de um
| uso inteiro.
*/

it('nao poda linhas permanentes na limpeza comum', function () {
    AiCache::factory()->expired()->create();
    $permanent = AiCache::factory()->permanent()->create();

    $this->artisan('ai:prune-cache')
        ->expectsOutputToContain('1 resposta(s) removida(s)')
        ->assertSuccessful();

    expect(AiCache::query()->count())->toBe(1)
        ->and(AiCache::query()->sole()->id)->toBe($permanent->id);
});

it('restaura a poda por purpose e exige purpose com all', function () {
    AiCache::factory()->permanent()->create(['purpose' => 'colors.comment_moderation']);
    AiCache::factory()->permanent()->create(['purpose' => 'ai']);

    $this->artisan('ai:prune-cache', ['--all' => true])
        ->expectsOutputToContain('--all exige --purpose')
        ->assertFailed();

    $this->artisan('ai:prune-cache', [
        '--purpose' => 'colors.comment_moderation',
        '--all' => true,
    ])->assertSuccessful();

    expect(AiCache::query()->count())->toBe(1)
        ->and(AiCache::query()->sole()->purpose)->toBe('ai');
});

it('poda apenas linhas expiradas do purpose escolhido', function () {
    AiCache::factory()->expired()->create(['purpose' => 'ai']);
    AiCache::factory()->expired()->create(['purpose' => 'colors.comment_moderation']);
    AiCache::factory()->permanent()->create(['purpose' => 'colors.comment_moderation']);

    $this->artisan('ai:prune-cache', [
        '--purpose' => 'colors.comment_moderation',
    ])->assertSuccessful();

    expect(AiCache::query()->count())->toBe(2)
        ->and(AiCache::query()->where('purpose', 'colors.comment_moderation')->count())->toBe(1);
});
