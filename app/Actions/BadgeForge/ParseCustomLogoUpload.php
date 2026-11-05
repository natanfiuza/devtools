<?php

namespace App\Actions\BadgeForge;

use App\Support\BadgeForge\CustomLogoSvgParser;
use App\Support\BadgeForge\InvalidCustomLogoException;
use App\Support\BadgeForge\ParsedCustomLogo;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Converte o upload de logo no shape sanitizado (ADR-078).
 *
 * A tela e a API JSON (F8, ADR-088) compartilham esta entrada: arquivo
 * ausente/invalido e SVG recusado viram 422 com a chave `logo` traduzida. O
 * conteudo cru nunca sai daqui - o que segue para o banco e so `viewBox`,
 * paths normalizados e o tamanho.
 */
final class ParseCustomLogoUpload
{
    public function __construct(private readonly CustomLogoSvgParser $parser) {}

    /**
     * O tipo aceita `array` porque `Request::file()` devolve a lista quando o
     * campo e multi-arquivo; um logo e sempre um unico arquivo.
     *
     * @param  UploadedFile|array<mixed>|null  $file
     *
     * @throws ValidationException
     */
    public function __invoke(UploadedFile|array|null $file): ParsedCustomLogo
    {
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            throw ValidationException::withMessages([
                'logo' => __('validation.file', ['attribute' => __('validation.attributes.logo')]),
            ]);
        }

        $contents = (string) file_get_contents((string) $file->getRealPath());

        try {
            return $this->parser->parse($contents, (int) $file->getSize());
        } catch (InvalidCustomLogoException $exception) {
            throw ValidationException::withMessages([
                'logo' => __('validation.custom_logo.'.$exception->reason),
            ]);
        }
    }
}
