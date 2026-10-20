<?php

use App\Enums\SchemaSource;
use App\Enums\SqlDialect;
use App\Models\AiCache;
use App\Support\Ai\AiCacheRepository;
use App\Support\Ai\AiResult;
use App\Support\Ai\Canonicalizer;
use App\Support\Ai\CanonicalPayload;
use App\Support\Ai\ContractValidator;
use App\Support\Ai\PayloadCodec;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function aiCachePayload(int $version = 1): CanonicalPayload
{
    return (new Canonicalizer)->canonicalize(
        SchemaSource::Ddl,
        SqlDialect::Mysql,
        'sql',
        'CREATE TABLE users (id int);',
        'Liste os usuarios',
        'model-a',
        $version,
    );
}

function aiCacheResult(): AiResult
{
    return new AiResult(
        'SELECT id FROM users',
        SqlDialect::Mysql,
        ['A tabela users tem os dados.'],
        [],
        ['users'],
        false,
        null,
        ['sql' => 'SELECT id FROM users'],
    );
}

function aiCacheRepository(): AiCacheRepository
{
    return new AiCacheRepository(new PayloadCodec, new ContractValidator);
}

it('guarda e devolve um cache valido, contando o hit', function () {
    $payload = aiCachePayload();
    $repository = aiCacheRepository();

    $cache = $repository->store($payload, aiCacheResult(), 800, 200);
    $hit = $repository->find($payload, SqlDialect::Mysql, 'sql');

    expect($hit)->not->toBeNull()
        ->and($hit?->cache->id)->toBe($cache->id)
        ->and($hit?->result->sql)->toBe('SELECT id FROM users')
        ->and($hit?->cache->tokens_in)->toBe(800);

    $repository->recordHit($cache);
    $cache->refresh();

    expect($cache->hits)->toBe(1)
        ->and($cache->last_hit_at)->not->toBeNull();
});

it('regravar o mesmo hash preserva hits e data de criacao', function () {
    $payload = aiCachePayload();
    $repository = aiCacheRepository();

    $cache = $repository->store($payload, aiCacheResult(), 800, 200);
    $repository->recordHit($cache);
    $cache->refresh();
    $createdAt = $cache->created_at;

    $again = $repository->store($payload, aiCacheResult(), 900, 100);
    $again->refresh();

    expect($again->id)->toBe($cache->id)
        ->and($again->hits)->toBe(1)
        ->and($again->tokens_in)->toBe(900)
        ->and($again->created_at?->equalTo($createdAt))->toBeTrue();
});

it('nao devolve cache expirado', function () {
    $payload = aiCachePayload();

    AiCache::factory()->expired()->create([
        'prompt_hash' => $payload->hash(),
        'canonical_payload' => (new PayloadCodec)->encode($payload),
    ]);

    expect(aiCacheRepository()->find($payload, SqlDialect::Mysql, 'sql'))
        ->toBeNull();
});

it('apaga cache com contrato corrompido e deixa o pedido seguir', function () {
    $payload = aiCachePayload();
    $repository = aiCacheRepository();

    $cache = $repository->store($payload, aiCacheResult(), 800, 200);
    $cache->update(['response_json' => '{}']);

    expect($repository->find($payload, SqlDialect::Mysql, 'sql'))
        ->toBeNull();

    $this->assertDatabaseMissing('ai_cache', ['id' => $cache->id]);
});

it('nao devolve resposta de payload diferente com o mesmo hash', function () {
    // O MD5 e chave de busca, nao prova de identidade (ADR-016): se a linha
    // tiver o mesmo hash e outro payload, a resposta nao pode ser servida.
    $payload = aiCachePayload();
    $outro = aiCachePayload(2);
    $repository = aiCacheRepository();

    $cache = $repository->store($payload, aiCacheResult(), 800, 200);
    $cache->update(['prompt_hash' => $outro->hash()]);

    expect($repository->find($outro, SqlDialect::Mysql, 'sql'))->toBeNull();
});

it('apaga somente as linhas expiradas', function () {
    $repository = aiCacheRepository();
    $valid = $repository->store(aiCachePayload(1), aiCacheResult(), 10, 5);
    $expired = $repository->store(aiCachePayload(2), aiCacheResult(), 10, 5);

    $expired->update(['expires_at' => now()->subMinute()]);

    expect($repository->deleteExpired())->toBe(1)
        ->and(AiCache::query()->find($valid->id))->not->toBeNull()
        ->and(AiCache::query()->find($expired->id))->toBeNull();
});
