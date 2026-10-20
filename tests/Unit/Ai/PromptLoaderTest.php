<?php

use App\Exceptions\Ai\PromptException;
use App\Support\Ai\PromptLoader;
use Tests\TestCase;

uses(TestCase::class);

it('carrega o prompt do alvo e extrai a versao', function () {
    $prompt = (new PromptLoader)->load('sql.v1.md');

    expect($prompt->template)->toBe('sql.v1.md')
        ->and($prompt->slug)->toBe('sql')
        ->and($prompt->version)->toBe(1)
        ->and($prompt->text)->toContain('Target: SQL');
});

it('aceita versoes de mais de um digito', function () {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ai-prompts-'.uniqid();
    mkdir($directory, 0777, true);
    file_put_contents($directory.DIRECTORY_SEPARATOR.'sql.v12.md', "Prompt v12\n");

    config(['ai.prompt_path' => $directory]);

    try {
        $prompt = (new PromptLoader)->load('sql.v12.md');

        expect($prompt->version)->toBe(12)
            ->and($prompt->text)->toBe('Prompt v12');
    } finally {
        unlink($directory.DIRECTORY_SEPARATOR.'sql.v12.md');
        rmdir($directory);
    }
});

it('recusa nome fora do padrao versionado', function () {
    $loader = new PromptLoader;

    foreach (['../README.md', 'sql.md', 'sql.v0.md', 'SQL.v1.md', 'sql.v1.txt'] as $template) {
        expect(fn () => $loader->load($template))
            ->toThrow(PromptException::class);
    }
});

it('falha de forma clara quando o arquivo nao existe ou esta vazio', function () {
    $loader = new PromptLoader;

    expect(fn () => $loader->load('nao_existe.v1.md'))
        ->toThrow(PromptException::class);

    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ai-prompts-'.uniqid();
    mkdir($directory, 0777, true);
    file_put_contents($directory.DIRECTORY_SEPARATOR.'vazio.v1.md', "\n");

    config(['ai.prompt_path' => $directory]);

    try {
        expect(fn () => $loader->load('vazio.v1.md'))
            ->toThrow(PromptException::class);
    } finally {
        unlink($directory.DIRECTORY_SEPARATOR.'vazio.v1.md');
        rmdir($directory);
    }
});
