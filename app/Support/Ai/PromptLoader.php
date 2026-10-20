<?php

namespace App\Support\Ai;

use App\Exceptions\Ai\PromptException;

/**
 * Le `resources/prompts/<slug>.v<N>.md`.
 *
 * O nome vem de `output_targets.prompt_template`, mas e validado de novo: o
 * padrao sem barras e sem `..` impede que uma linha adulterada no banco leia
 * um arquivo fora do diretorio de prompts.
 */
final class PromptLoader
{
    private const PATTERN = '/^([a-z0-9_]+)\.v([1-9][0-9]*)\.md$/';

    public function load(string $template): Prompt
    {
        if (preg_match(self::PATTERN, $template, $matches) !== 1) {
            throw PromptException::invalidName($template);
        }

        $directory = config('ai.prompt_path');

        if (! is_string($directory) || $directory === '') {
            throw PromptException::notFound($template);
        }

        $path = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$template;

        if (! is_file($path) || ! is_readable($path)) {
            throw PromptException::notFound($template);
        }

        $text = file_get_contents($path);

        if ($text === false || trim($text) === '') {
            throw PromptException::empty($template);
        }

        return new Prompt(
            $template,
            $matches[1],
            (int) $matches[2],
            trim($text),
        );
    }
}
