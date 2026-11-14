<?php

namespace App\Support\Colors;

use App\Models\ColorPalette;
use App\Models\User;

/**
 * Shapes do perfil publico e do autor de uma paleta (F6, ADR-134).
 *
 * So o que a vitrine precisa: nome, username, avatar e contadores. E-mail,
 * id de sessao e qualquer dado de conta ficam de fora. O autor de uma paleta
 * desaparece quando a conta nao tem username, foi suspensa ou anonimizada.
 */
final class ProfilePresenter
{
    public function __construct(private readonly PalettePresenter $palettes) {}

    /**
     * @return array<string, mixed>
     */
    public function profile(User $user): array
    {
        return [
            'id' => (int) $user->getKey(),
            'name' => $user->name,
            'username' => $user->username,
            'avatar' => self::avatar($user),
            'followers_count' => $user->followers()->active()->count(),
            'following_count' => $user->following()->active()->count(),
            'member_since' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * Cartao do perfil: a mesma linha de Minhas Paletas com a curtida ativa.
     *
     * @return array<string, mixed>
     */
    public function paletteRow(ColorPalette $palette): array
    {
        return array_merge($this->palettes->row($palette), [
            'likes_count' => self::likesCount($palette),
        ]);
    }

    /**
     * URL publica do avatar, quando a conta tem foto.
     */
    public static function avatar(User $user): ?string
    {
        if ($user->photo_uuid === null) {
            return null;
        }

        return route('users.profile.photo', ['uuid' => $user->photo_uuid]);
    }

    /**
     * Curtidas ativas; usa o `withCount` quando a consulta ja o trouxe.
     */
    public static function likesCount(ColorPalette $palette): int
    {
        $attributes = $palette->getAttributes();

        if (array_key_exists('likes_count', $attributes)) {
            return (int) $attributes['likes_count'];
        }

        return $palette->likes()->active()->count();
    }
}
