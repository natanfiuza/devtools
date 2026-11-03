<?php

namespace App\Support\BadgeForge;

use DOMDocument;
use DOMElement;

/**
 * Sanitizador de SVG para logos personalizados (ADR-078).
 *
 * Nao existe "SVG seguro": o parser aceita apenas `<svg>`, `<g>` e `<path>`,
 * com um allowlist minimo de atributos, e devolve somente viewBox + paths.
 * O arquivo enviado nao e persistido nem servido. Qualquer desvio vira
 * `InvalidCustomLogoException` com um codigo estavel para o 422 traduzido.
 *
 * O desenho e recolorido por `logoColor`, entao `fill`/`stroke`/`style` do
 * arquivo sao ignorados (e `style` e recusado). `fill-rule`/`clip-rule` sao
 * preservados porque mudam a geometria visivel.
 */
final class CustomLogoSvgParser
{
    private const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    private const XMLNS_NAMESPACE = 'http://www.w3.org/2000/xmlns/';

    /** Elementos permitidos abaixo do `<svg>` raiz. */
    private const ALLOWED_ELEMENTS = ['g', 'path'];

    private const RULE_VALUES = ['nonzero', 'evenodd'];

    /** `d` aceita apenas comandos e numeros de path SVG. */
    public const PATH_PATTERN = '/^[MmLlHhVvCcSsQqTtAaZz0-9+\-.,eE\s]+$/';

    private const NUMBER_PATTERN = '/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/';

    /**
     * @throws InvalidCustomLogoException
     */
    public function parse(string $contents, int $sizeBytes): ParsedCustomLogo
    {
        $maxBytes = (int) config('badgeforge.custom_logos.max_bytes', 262144);

        if ($sizeBytes > $maxBytes || strlen($contents) > $maxBytes) {
            throw new InvalidCustomLogoException('too_large');
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            throw new InvalidCustomLogoException('invalid_encoding');
        }

        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $contents) === 1) {
            throw new InvalidCustomLogoException('doctype_not_allowed');
        }

        // A declaracao XML e aceita; qualquer outra PI e recusada.
        if (preg_match('/<\?(?!xml(?:\s|\?>))/i', $contents) === 1) {
            throw new InvalidCustomLogoException('processing_instruction_not_allowed');
        }

        $document = $this->load($contents);
        $root = $document->documentElement;

        if (! $root instanceof DOMElement || strtolower((string) $root->localName) !== 'svg') {
            throw new InvalidCustomLogoException('invalid_root');
        }

        $this->assertNamespace($root);
        $this->assertRootAttributes($root);

        $viewBox = $this->readViewBox($root);

        /** @var list<LogoPath> $paths */
        $paths = [];
        $totalLength = 0;

        $this->walk($root, 1, null, null, $paths, $totalLength);

        if ($paths === []) {
            throw new InvalidCustomLogoException('missing_paths');
        }

        return new ParsedCustomLogo($viewBox, $paths, $sizeBytes);
    }

    /**
     * @throws InvalidCustomLogoException
     */
    private function load(string $contents): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new DOMDocument;
            $document->preserveWhiteSpace = false;

            $loaded = $document->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS);
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded || $errors !== []) {
            throw new InvalidCustomLogoException('malformed');
        }

        return $document;
    }

    /**
     * @throws InvalidCustomLogoException
     */
    private function assertNamespace(DOMElement $element): void
    {
        $namespace = $element->namespaceURI;

        if ($namespace !== null && $namespace !== '' && $namespace !== self::SVG_NAMESPACE) {
            throw new InvalidCustomLogoException('invalid_root');
        }
    }

    /**
     * O `<svg>` raiz e recriado pelo renderer, entao `fill`/`width`/`height`
     * sao aceitos e ignorados. `xmlns` so e permitido como declaracao padrao
     * apontando para o namespace SVG (ou vazio).
     *
     * @throws InvalidCustomLogoException
     */
    private function assertRootAttributes(DOMElement $root): void
    {
        $allowed = [
            'viewbox', 'xmlns', 'width', 'height', 'fill', 'class', 'id',
            'version', 'role', 'aria-label', 'aria-hidden', 'focusable',
            'preserveaspectratio',
        ];

        // Sem atributos, o mapa vem vazio (nunca null): o loop nao roda.
        foreach ($root->attributes as $attribute) {
            $namespace = $attribute->namespaceURI;

            if ($namespace !== null && $namespace !== '') {
                $isDefaultSvgXmlns = $namespace === self::XMLNS_NAMESPACE
                    && strtolower($attribute->name) === 'xmlns'
                    && ($attribute->value === '' || $attribute->value === self::SVG_NAMESPACE);

                if (! $isDefaultSvgXmlns) {
                    throw new InvalidCustomLogoException('unsupported_attribute');
                }

                continue;
            }

            if (! in_array(strtolower($attribute->name), $allowed, true)) {
                throw new InvalidCustomLogoException('unsupported_attribute');
            }

            if (preg_match('/url\s*\(/i', $attribute->value) === 1) {
                throw new InvalidCustomLogoException('unsafe_value');
            }
        }
    }

    /**
     * @throws InvalidCustomLogoException
     */
    private function readViewBox(DOMElement $root): string
    {
        if (! $root->hasAttribute('viewBox')) {
            throw new InvalidCustomLogoException('invalid_viewbox');
        }

        $viewBox = self::normalizeViewBox($root->getAttribute('viewBox'));

        if ($viewBox === null) {
            throw new InvalidCustomLogoException('invalid_viewbox');
        }

        return $viewBox;
    }

    /**
     * Normaliza `viewBox` para quatro numeros separados por espaco, com
     * largura/altura positivas e extensao limitada. `null` = invalido.
     */
    public static function normalizeViewBox(string $raw): ?string
    {
        $parts = array_values(array_filter(
            preg_split('/[\s,]+/', trim($raw)) ?: [],
            static fn (string $part): bool => $part !== '',
        ));

        if (count($parts) !== 4) {
            return null;
        }

        $numbers = [];

        foreach ($parts as $part) {
            if (preg_match(self::NUMBER_PATTERN, $part) !== 1) {
                return null;
            }

            $number = (float) $part;

            if (! is_finite($number)) {
                return null;
            }

            $numbers[] = $number;
        }

        $maxExtent = (float) config('badgeforge.custom_logos.max_viewbox_extent', 10000);

        foreach ($numbers as $number) {
            if (abs($number) > $maxExtent) {
                return null;
            }
        }

        if ($numbers[2] <= 0 || $numbers[3] <= 0) {
            return null;
        }

        return implode(' ', $parts);
    }

    /**
     * Percorre os filhos de um elemento, achatando `<g>` na lista de paths.
     *
     * @param  list<LogoPath>  $paths
     *
     * @throws InvalidCustomLogoException
     */
    private function walk(
        DOMElement $element,
        int $depth,
        ?string $fillRule,
        ?string $clipRule,
        array &$paths,
        int &$totalLength,
    ): void {
        if ($depth > (int) config('badgeforge.custom_logos.max_document_depth', 8)) {
            throw new InvalidCustomLogoException('too_deep');
        }

        foreach ($element->childNodes as $child) {
            if ($child->nodeType === XML_COMMENT_NODE) {
                continue;
            }

            if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                if (trim((string) $child->nodeValue) !== '') {
                    throw new InvalidCustomLogoException('text_not_allowed');
                }

                continue;
            }

            if ($child->nodeType === XML_PI_NODE) {
                throw new InvalidCustomLogoException('processing_instruction_not_allowed');
            }

            if (! $child instanceof DOMElement) {
                throw new InvalidCustomLogoException('unsupported_element');
            }

            $name = strtolower((string) $child->localName);

            if (! in_array($name, self::ALLOWED_ELEMENTS, true)) {
                throw new InvalidCustomLogoException('unsupported_element');
            }

            $this->assertNamespace($child);

            if ($name === 'g') {
                [$groupFillRule, $groupClipRule] = $this->readRules($child);
                $nextFillRule = $groupFillRule ?? $fillRule;
                $nextClipRule = $groupClipRule ?? $clipRule;

                $this->walk($child, $depth + 1, $nextFillRule, $nextClipRule, $paths, $totalLength);

                continue;
            }

            $this->appendPath($child, $fillRule, $clipRule, $paths, $totalLength);
        }
    }

    /**
     * @param  list<LogoPath>  $paths
     *
     * @throws InvalidCustomLogoException
     */
    private function appendPath(
        DOMElement $path,
        ?string $fillRule,
        ?string $clipRule,
        array &$paths,
        int &$totalLength,
    ): void {
        foreach ($path->childNodes as $child) {
            if ($child->nodeType === XML_COMMENT_NODE) {
                continue;
            }

            if (($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE)
                && trim((string) $child->nodeValue) === '') {
                continue;
            }

            throw new InvalidCustomLogoException('text_not_allowed');
        }

        if (! $path->hasAttribute('d')) {
            throw new InvalidCustomLogoException('invalid_path');
        }

        $data = trim($path->getAttribute('d'));

        if (! self::isSafePathData($data)) {
            throw new InvalidCustomLogoException('invalid_path');
        }

        $maxPathLength = (int) config('badgeforge.custom_logos.max_path_length', 4000);
        $maxPaths = (int) config('badgeforge.custom_logos.max_paths', 200);
        $maxTotalLength = (int) config('badgeforge.custom_logos.max_total_path_length', 100000);

        if (strlen($data) > $maxPathLength) {
            throw new InvalidCustomLogoException('path_too_long');
        }

        $totalLength += strlen($data);

        if ($totalLength > $maxTotalLength) {
            throw new InvalidCustomLogoException('total_path_too_long');
        }

        if (count($paths) >= $maxPaths) {
            throw new InvalidCustomLogoException('too_many_paths');
        }

        [$pathFillRule, $pathClipRule] = $this->readRules($path);

        $paths[] = new LogoPath(
            d: $data,
            fillRule: $pathFillRule ?? $fillRule,
            clipRule: $pathClipRule ?? $clipRule,
        );
    }

    /**
     * Le `fill-rule`/`clip-rule` de um elemento `g` ou `path`.
     *
     * @return array{0: string|null, 1: string|null}
     *
     * @throws InvalidCustomLogoException
     */
    private function readRules(DOMElement $element): array
    {
        $this->assertAttributes($element, $element->localName === 'g'
            ? ['fill-rule', 'clip-rule', 'fill', 'class', 'id']
            : ['d', 'fill-rule', 'clip-rule', 'fill', 'class', 'id']);

        return [
            $this->ruleAttribute($element, 'fill-rule'),
            $this->ruleAttribute($element, 'clip-rule'),
        ];
    }

    /**
     * @throws InvalidCustomLogoException
     */
    private function ruleAttribute(DOMElement $element, string $name): ?string
    {
        if (! $element->hasAttribute($name)) {
            return null;
        }

        $value = strtolower(trim($element->getAttribute($name)));

        if (! in_array($value, self::RULE_VALUES, true)) {
            throw new InvalidCustomLogoException('unsupported_attribute');
        }

        return $value;
    }

    /**
     * @param  list<string>  $allowed
     *
     * @throws InvalidCustomLogoException
     */
    private function assertAttributes(DOMElement $element, array $allowed): void
    {
        foreach ($element->attributes as $attribute) {
            if ($attribute->namespaceURI !== null && $attribute->namespaceURI !== '') {
                throw new InvalidCustomLogoException('unsupported_attribute');
            }

            $name = strtolower($attribute->name);

            if (! in_array($name, $allowed, true)) {
                throw new InvalidCustomLogoException('unsupported_attribute');
            }

            if (preg_match('/url\s*\(/i', $attribute->value) === 1) {
                throw new InvalidCustomLogoException('unsafe_value');
            }
        }
    }

    /**
     * Regra de `d` compartilhada com a leitura do banco: charset, comando
     * inicial e tamanho. A contagem total e feita pelo chamador.
     */
    public static function isSafePathData(string $data): bool
    {
        if ($data === '' || preg_match(self::PATH_PATTERN, $data) !== 1) {
            return false;
        }

        $first = $data[0];

        return $first === 'M' || $first === 'm';
    }
}
