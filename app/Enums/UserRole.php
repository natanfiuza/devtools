<?php

namespace App\Enums;

/**
 * Papel da conta (docs/SPEC.md secao 7).
 *
 * Sao dois, sem tabela de papeis e sem permissao granular: o admin entra no
 * painel administrativo, o usuario so na propria area. O papel e escrito pelo
 * comando `devtools:admin` ou pela acao do painel - nunca vem do request (por
 * isso `role` fica fora do #[Fillable] do modelo).
 */
enum UserRole: string
{
    case User = 'user';
    case Admin = 'admin';

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }
}
