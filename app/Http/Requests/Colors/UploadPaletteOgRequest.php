<?php

namespace App\Http\Requests\Colors;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload da imagem OG gerada no navegador (F4, ADR-125).
 *
 * O servidor nao desenha nada: aceita apenas PNG de verdade, nas dimensoes
 * exatas do cartao social e dentro do teto de bytes da configuracao. A
 * validacao de dimensoes tambem garante que o arquivo e uma imagem legivel.
 */
final class UploadPaletteOgRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $width = (int) config('colors.og.width', 1200);
        $height = (int) config('colors.og.height', 630);
        $maxKb = max(1, intdiv((int) config('colors.og.max_bytes', 2 * 1024 * 1024), 1024));

        return [
            'image' => [
                'required',
                'file',
                'image',
                'mimes:png',
                'mimetypes:image/png',
                'max:'.$maxKb,
                'dimensions:width='.$width.',height='.$height,
            ],
        ];
    }
}
