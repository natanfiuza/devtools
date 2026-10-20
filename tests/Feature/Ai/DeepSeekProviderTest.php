<?php

use App\Contracts\Ai\LlmProvider;
use App\Exceptions\Ai\ProviderException;
use App\Services\Ai\DeepSeekProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'ai.base_url' => 'https://api.deepseek.test',
        'ai.model' => 'deepseek-chat-test',
        'ai.api_key' => 'test-key',
        'ai.connect_timeout' => 5,
        'ai.timeout' => 10,
        'ai.temperature' => 0.2,
        'ai.max_tokens' => 1000,
    ]);
});

function aiProvider(): LlmProvider
{
    return app(LlmProvider::class);
}

it('liga o contrato ao cliente HTTP fino do DeepSeek', function () {
    expect(aiProvider())->toBeInstanceOf(DeepSeekProvider::class);
});

it('envia o pedido em JSON mode e devolve conteudo e usage', function () {
    Http::fake([
        'https://api.deepseek.test/chat/completions' => Http::response([
            'model' => 'deepseek-chat',
            'choices' => [
                ['message' => ['content' => '{"sql":"SELECT 1"}']],
            ],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 7],
        ], 200),
    ]);

    $completion = aiProvider()->complete([
        ['role' => 'system', 'content' => 'Responda em JSON.'],
        ['role' => 'user', 'content' => 'SCHEMA: x'],
    ]);

    expect($completion->content)->toBe('{"sql":"SELECT 1"}')
        ->and($completion->tokensIn)->toBe(12)
        ->and($completion->tokensOut)->toBe(7)
        ->and($completion->model)->toBe('deepseek-chat');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.deepseek.test/chat/completions'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'deepseek-chat-test'
            && $request['response_format'] === ['type' => 'json_object']
            && $request['messages'][0]['content'] === 'Responda em JSON.';
    });
});

it('estima os tokens quando o usage nao vem', function () {
    Http::fake([
        '*' => Http::response([
            'choices' => [
                ['message' => ['content' => '{"sql":"SELECT 1"}']],
            ],
        ], 200),
    ]);

    $completion = aiProvider()->complete([
        ['role' => 'user', 'content' => str_repeat('a', 80)],
    ]);

    expect($completion->tokensIn)->toBeGreaterThan(0)
        ->and($completion->tokensOut)->toBeGreaterThan(0);
});

it('marca 429 e 5xx como retentaveis sem expor o corpo', function (int $status) {
    Http::fake([
        '*' => Http::response('schema secreto do cliente', $status),
    ]);

    $exception = null;

    try {
        aiProvider()->complete([['role' => 'user', 'content' => 'x']]);
    } catch (ProviderException $caught) {
        $exception = $caught;
    }

    expect($exception)->not->toBeNull()
        ->and($exception?->retryable)->toBeTrue()
        ->and($exception?->reason)->toBe('http_'.$status)
        ->and($exception?->getMessage())->not->toContain('schema secreto');
})->with([429, 500, 503]);

it('marca 401 e 422 como definitivos', function (int $status) {
    Http::fake([
        '*' => Http::response('', $status),
    ]);

    $exception = null;

    try {
        aiProvider()->complete([['role' => 'user', 'content' => 'x']]);
    } catch (ProviderException $caught) {
        $exception = $caught;
    }

    expect($exception)->not->toBeNull()
        ->and($exception?->retryable)->toBeFalse()
        ->and($exception?->reason)->toBe('http_'.$status);
})->with([401, 422]);

it('marca JSON invalido como resposta invalida e nao copia o corpo', function () {
    Http::fake([
        '*' => Http::response('nao e json schema secreto', 200),
    ]);

    $exception = null;

    try {
        aiProvider()->complete([['role' => 'user', 'content' => 'x']]);
    } catch (ProviderException $caught) {
        $exception = $caught;
    }

    expect($exception)->not->toBeNull()
        ->and($exception?->reason)->toBe('invalid_response')
        ->and($exception?->getMessage())->not->toContain('schema secreto');
});

it('marca falha de conexao como retentavel', function () {
    Http::fake(function (): never {
        throw new ConnectionException('tempo esgotado');
    });

    $exception = null;

    try {
        aiProvider()->complete([['role' => 'user', 'content' => 'x']]);
    } catch (ProviderException $caught) {
        $exception = $caught;
    }

    expect($exception)->not->toBeNull()
        ->and($exception?->retryable)->toBeTrue()
        ->and($exception?->reason)->toBe('connection');
});
