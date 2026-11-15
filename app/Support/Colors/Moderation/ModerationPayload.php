<?php

namespace App\Support\Colors\Moderation;

use App\Exceptions\Ai\PayloadCodecException;

/**
 * Identidade de uma moderacao em cache (F7, ADR-140).
 *
 * O hash cobre o texto exato (com trim), o idioma, o modelo, a versao do
 * prompt e a versao da politica. Dois comentarios iguais no mesmo cenário caem
 * no mesmo cache; mudar qualquer um dos campos gera uma chamada nova.
 *
 * O texto do comentario fica comprimido em `ai_cache` enquanto a linha existir
 * (por padrão, permanentemente). Nunca vai para log nem para excecao.
 */
final readonly class ModerationPayload
{
    /**
     * Uso da tabela compartilhada `ai_cache` (o AI SQL Builder usa `ai`).
     */
    public const PURPOSE = 'colors.comment_moderation';

    public function __construct(
        public string $body,
        public string $locale,
        public string $model,
        public int $promptVersion,
        public int $policyVersion,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $body = $data['body'] ?? null;
        $locale = $data['locale'] ?? null;
        $model = $data['model'] ?? null;
        $promptVersion = $data['prompt_version'] ?? null;
        $policyVersion = $data['policy_version'] ?? null;

        if (($data['purpose'] ?? null) !== self::PURPOSE
            || ! is_string($body)
            || ! is_string($locale)
            || ! is_string($model)
            || ! is_int($promptVersion)
            || ! is_int($policyVersion)) {
            throw PayloadCodecException::invalidShape();
        }

        return new self($body, $locale, $model, $promptVersion, $policyVersion);
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        $data = [
            'body' => $this->body,
            'locale' => $this->locale,
            'model' => $this->model,
            'policy_version' => $this->policyVersion,
            'prompt_version' => $this->promptVersion,
            'purpose' => self::PURPOSE,
        ];

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
     * MD5 e identidade de cache, nao seguranca (ADR-016). O payload completo e
     * comparado na leitura, entao uma colisao vira cache miss.
     */
    public function hash(): string
    {
        return md5($this->json());
    }
}
