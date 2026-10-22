<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiIdentity;
use App\Http\Controllers\Controller;
use App\Support\Quota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Identidade, plano e cota do dono do token (ADR-050).
 *
 * E o primeiro pedido de uma integracao: confirma a quem o token pertence e
 * quanto ainda cabe no periodo. Nenhum dado sensivel sai - nem role, nem
 * segredo de 2FA.
 */
final class MeController extends Controller
{
    use ResolvesApiIdentity;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->apiUser($request);
        $plan = $user->effectivePlan();

        return response()->json([
            'data' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
                'plan' => [
                    'slug' => $plan->slug,
                    'name' => $plan->name,
                    'is_free' => $plan->isFree(),
                ],
                'quota' => Quota::for($user)->toArray(),
            ],
        ]);
    }
}
