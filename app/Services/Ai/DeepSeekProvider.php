<?php

namespace App\Services\Ai;

use App\Contracts\Ai\LlmProvider;
use App\Exceptions\Ai\ProviderException;
use App\Support\Ai\AiConfiguration;
use App\Support\Ai\LlmCompletion;
use App\Support\Ai\TokenEstimator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * Cliente HTTP fino do DeepSeek (API compativel com OpenAI).
 *
 * Nao usamos SDK: a API e um POST JSON e o projeto nao precisa carregar uma
 * dependencia inteira para isso (ADR-039). O corpo do pedido leva o schema e a
 * descricao; por isso nada dele, nem da resposta, vai para log.
 *
 * Quando o `usage` vem incompleto, a estimativa local preenche o que faltou -
 * um valor zero faria o plano pago devolver a reserva inteira por um trabalho
 * que o provedor cobrou.
 */
final class DeepSeekProvider implements LlmProvider
{
    public function __construct(
        private readonly TokenEstimator $estimator,
    ) {}

    public function complete(array $messages): LlmCompletion
    {
        AiConfiguration::ensureAvailable();

        $url = rtrim(AiConfiguration::baseUrl(), '/').'/chat/completions';

        try {
            $response = Http::withToken(AiConfiguration::apiKey())
                ->acceptJson()
                ->asJson()
                ->connectTimeout(max(1, (int) config('ai.connect_timeout', 10)))
                ->timeout(max(1, (int) config('ai.timeout', 60)))
                ->post($url, [
                    'model' => AiConfiguration::model(),
                    'messages' => $messages,
                    'temperature' => (float) config('ai.temperature', 0.2),
                    'max_tokens' => max(1, (int) config('ai.max_tokens', 2000)),
                    'response_format' => ['type' => 'json_object'],
                ]);
        } catch (ConnectionException) {
            throw ProviderException::retryable('connection');
        }

        if (! $response->successful()) {
            throw ProviderException::fromStatus($response->status());
        }

        try {
            $data = json_decode($response->body(), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ProviderException::invalidResponse();
        }

        if (! is_array($data)) {
            throw ProviderException::invalidResponse();
        }

        $choices = $data['choices'] ?? null;
        $choice = is_array($choices) ? ($choices[0] ?? null) : null;
        $message = is_array($choice) ? ($choice['message'] ?? null) : null;
        $content = is_array($message) ? ($message['content'] ?? null) : null;

        if (! is_string($content) || trim($content) === '') {
            throw ProviderException::invalidResponse();
        }

        $usage = $data['usage'] ?? null;
        $usage = is_array($usage) ? $usage : [];

        $tokensIn = is_int($usage['prompt_tokens'] ?? null)
            ? $usage['prompt_tokens']
            : $this->estimator->inputTokens($messages);

        $tokensOut = is_int($usage['completion_tokens'] ?? null)
            ? $usage['completion_tokens']
            : $this->estimator->fromCharacters(mb_strlen($content, 'UTF-8'));

        $providerModel = $data['model'] ?? null;
        $model = is_string($providerModel) && trim($providerModel) !== ''
            ? trim($providerModel)
            : AiConfiguration::model();

        return new LlmCompletion(
            trim($content),
            max(0, $tokensIn),
            max(0, $tokensOut),
            $model,
        );
    }
}
