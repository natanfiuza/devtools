<?php

namespace App\Support;

use App\Exceptions\Ai\AiInputException;
use App\Exceptions\Ai\AiUnavailableException;
use App\Exceptions\Ai\QuotaExceededException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Envelope unico de erro da API (ADR-042).
 *
 * O `code` e o contrato que a tela traduz; `message` e apenas o fallback
 * tecnico do servidor. `details` carrega o que ajuda a corrigir o pedido
 * (campo invalido, unidade da cota, erros de validacao). O corpo de resposta do
 * provedor de IA nunca chega aqui.
 */
final class ApiError
{
    /**
     * @param  array<string, mixed>|null  $details
     * @param  array<string, string>  $headers
     */
    public static function response(
        string $code,
        string $message,
        int $status,
        ?array $details = null,
        array $headers = [],
    ): JsonResponse {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== null) {
            $error['details'] = $details;
        }

        return response()->json(['error' => $error], $status, $headers);
    }

    /**
     * Converte excecoes conhecidas para o envelope. Devolve nulo para o que
     * nao pertence ao contrato da API, deixando o handler padrao responder.
     */
    public static function from(Throwable $exception): ?JsonResponse
    {
        return match (true) {
            $exception instanceof AiInputException => self::response(
                $exception->reason,
                $exception->getMessage(),
                422,
                ['field' => $exception->field],
            ),
            $exception instanceof QuotaExceededException => self::response(
                'quota_exceeded',
                $exception->getMessage(),
                402,
                [
                    'unit' => $exception->unit->value,
                    'required' => $exception->required,
                ],
            ),
            $exception instanceof AiUnavailableException => self::response(
                'ai_unavailable',
                $exception->getMessage(),
                503,
            ),
            $exception instanceof ValidationException => self::response(
                'validation_error',
                'The given data was invalid.',
                422,
                ['errors' => $exception->errors()],
            ),
            $exception instanceof AuthenticationException => self::response(
                'unauthenticated',
                'Unauthenticated.',
                401,
            ),
            $exception instanceof AccessDeniedHttpException => self::response(
                'forbidden',
                'This action is unauthorized.',
                403,
            ),
            $exception instanceof AuthorizationException => self::response(
                'forbidden',
                $exception->getMessage() !== '' ? $exception->getMessage() : 'This action is unauthorized.',
                403,
            ),
            $exception instanceof TokenMismatchException => self::response(
                'csrf_token_mismatch',
                'CSRF token mismatch.',
                419,
            ),
            $exception instanceof ThrottleRequestsException => self::response(
                'rate_limited',
                'Too many requests.',
                429,
                null,
                $exception->getHeaders(),
            ),
            $exception instanceof ModelNotFoundException,
            $exception instanceof NotFoundHttpException => self::response(
                'not_found',
                'Resource not found.',
                404,
            ),
            default => null,
        };
    }
}
