<?php

namespace App\Http\Controllers\Api\Colors;

use App\Actions\Colors\FollowUser;
use App\Actions\Colors\UnfollowUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Seguir e deixar de seguir contas (F6, ADR-136).
 *
 * Exige login. O alvo precisa ser uma conta existente, ativa, nao anonimizada
 * e com username; qualquer outro caso responde 404, sem revelar o motivo.
 * Seguir a si mesmo responde 403. A resposta nao carrega e-mail, username do
 * alvo nem identificador de sessao: apenas o estado e o total de seguidores.
 */
class UserFollowController extends Controller
{
    public function store(
        Request $request,
        User $user,
        FollowUser $follow,
    ): JsonResponse {
        $follower = $this->account($request);
        $this->authorizeTarget($follower, $user);

        $follow($follower, $user);

        return response()->json($this->payload($user, true));
    }

    public function destroy(
        Request $request,
        User $user,
        UnfollowUser $unfollow,
    ): JsonResponse {
        $follower = $this->account($request);
        $this->authorizeTarget($follower, $user);

        $unfollow($follower, $user);

        return response()->json($this->payload($user, false));
    }

    private function account(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function authorizeTarget(User $follower, User $target): void
    {
        if ((int) $follower->getKey() === (int) $target->getKey()) {
            throw new AccessDeniedHttpException;
        }

        abort_unless(
            $target->username !== null
                && ! $target->isSuspended()
                && ! $target->isAnonymized(),
            404,
        );
    }

    /**
     * @return array{following: bool, followers_count: int}
     */
    private function payload(User $target, bool $following): array
    {
        return [
            'following' => $following,
            'followers_count' => $target->followers()->active()->count(),
        ];
    }
}
