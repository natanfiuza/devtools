<?php

use App\Http\Controllers\BadgeForge\PersonalBadgeController;
use Illuminate\Support\Facades\Route;

/*
| Imagens publicas fora de `api/` (F4, ADR-081).
|
| Este arquivo e carregado pelo `then` do `withRouting` sem o grupo `web`: a
| badge pessoal e stateless, sem cookie de sessao nem CSRF. O username e o
| ULID juntos identificam o recurso; o erro sai no envelope `ApiError` (404
| `not_found`, 503 `badge_unavailable`) e nunca na pagina Inertia de erro
| (F8, ADR-089). O unico throttle e o cap por IP `badgeforge-images`, que a
| badge divide com as demais imagens anonimas.
*/
Route::get('b/{username}/{badgeAsset}.{ext}', PersonalBadgeController::class)
    ->where('username', '[A-Za-z0-9_][A-Za-z0-9._-]{0,39}')
    ->whereUlid('badgeAsset')
    ->where('ext', 'svg|png')
    ->middleware('throttle:badgeforge-images')
    ->name('badges.personal');
