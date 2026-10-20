<?php

namespace App\Support\Ai;

use App\Enums\SchemaSource;
use App\Enums\SqlDialect;
use App\Exceptions\Ai\PayloadCodecException;

/**
 * Tudo que define a identidade de uma resposta do AI SQL Builder.
 *
 * O JSON e canonico: chaves em ordem alfabetica e sem escapar barras/acentos.
 * O MD5 dele e o `prompt_hash` - dois pedidos so caem no mesmo cache se
 * dialeto, alvo, schema, descricao, modelo e versao do prompt forem iguais.
 */
final readonly class CanonicalPayload
{
    public function __construct(
        public SchemaSource $schemaSource,
        public SqlDialect $dialect,
        public string $target,
        public string $schema,
        public string $description,
        public string $model,
        public int $promptVersion,
    ) {}

    /**
     * Reconstroi o payload vindo do cache. A forma e conferida campo a campo
     * para um payload corrompido virar cache miss, nunca uma chamada torta.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $source = $data['schema_source'] ?? null;
        $dialect = $data['dialect'] ?? null;
        $target = $data['target'] ?? null;
        $schema = $data['schema'] ?? null;
        $description = $data['description'] ?? null;
        $model = $data['model'] ?? null;
        $version = $data['prompt_version'] ?? null;

        if (! is_string($source) || ! is_string($dialect) || ! is_string($target)
            || ! is_string($schema) || ! is_string($description) || ! is_string($model)
            || ! is_int($version)) {
            throw PayloadCodecException::invalidShape();
        }

        $schemaSource = SchemaSource::tryFrom($source);
        $sqlDialect = SqlDialect::tryFrom($dialect);

        if ($schemaSource === null || $sqlDialect === null) {
            throw PayloadCodecException::invalidShape();
        }

        return new self($schemaSource, $sqlDialect, $target, $schema, $description, $model, $version);
    }

    /**
     * @return array{
     *     description: string,
     *     dialect: string,
     *     model: string,
     *     prompt_version: int,
     *     schema: string,
     *     schema_source: string,
     *     target: string
     * }
     */
    public function toArray(): array
    {
        $data = [
            'description' => $this->description,
            'dialect' => $this->dialect->value,
            'model' => $this->model,
            'prompt_version' => $this->promptVersion,
            'schema' => $this->schema,
            'schema_source' => $this->schemaSource->value,
            'target' => $this->target,
        ];

        // O array ja nasce ordenado; o ksort documenta a regra para quem
        // acrescentar um campo no futuro.
        ksort($data);

        return $data;
    }

    public function json(): string
    {
        return json_encode(
            $this->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * MD5 de proposito (ADR-016): o hash nao e seguranca, e identidade de
     * cache - precisa ser o mesmo em PHP e no cliente, sem depender de salt.
     */
    public function hash(): string
    {
        return md5($this->json());
    }
}
