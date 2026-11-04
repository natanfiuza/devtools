<?php

use App\Http\Controllers\BadgeForge\PersonalBadgeController;
use Illuminate\Support\Facades\Route;

/*
| Imagens publicas fora de `api/` (F4, ADR-081).
|
| Este arquivo e carregado pelo `then` do `withRouting` sem o grupo `web`: a
| badge pessoal e stateless, sem cookie de sessao, CSRF ou throttle. O
| username e o ULID juntos identificam o recurso; o controller responde 404
| direto (corpo vazio) para nao cair na pagina Inertia de erro.
*/
Route::get('b/{username}/{badgeAsset}.{ext}', PersonalBadgeController::class)
    ->where('username', '[A-Za-z0-9_][A-Za-z0-9._-]{0,39}')
    ->whereUlid('badgeAsset')
    ->where('ext', 'svg|png')
    ->name('badges.personal');
