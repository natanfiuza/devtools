<?php

namespace App\Support\BadgeForge\Endpoint;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

/**
 * Sink de resposta com teto de bytes (F5, ADR-084).
 *
 * O handler curl nao tem limite de download proprio: este sink aborta a
 * escrita assim que o corpo passa de `endpoint.max_body_bytes`, inclusive em
 * resposta chunked sem `Content-Length`. O Guzzle embrulha a excecao numa
 * `RequestException` e o cliente a converte em `too_large`.
 */
final class LimitedResponseSink implements StreamInterface
{
    use StreamDecoratorTrait;

    private int $written = 0;

    public function __construct(
        private readonly StreamInterface $stream,
        private readonly int $limit,
    ) {}

    public function write(string $string): int
    {
        $this->written += strlen($string);

        if ($this->written > $this->limit) {
            throw new ResponseBodyTooLarge;
        }

        return $this->stream->write($string);
    }
}
