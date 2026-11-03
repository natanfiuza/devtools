<?php

namespace App\Http\Controllers\BadgeForge;

use App\Actions\BadgeForge\StoreCustomLogo;
use App\Http\Controllers\Controller;
use App\Http\Requests\BadgeForge\StoreCustomLogoRequest;
use App\Http\Requests\BadgeForge\UpdateCustomLogoRequest;
use App\Models\BadgeCustomLogo;
use App\Models\User;
use App\Support\BadgeForge\CustomLogoRepository;
use App\Support\BadgeForge\CustomLogoSvgParser;
use App\Support\BadgeForge\InvalidCustomLogoException;
use App\Support\BadgeForge\LogoPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Logos personalizados da conta (ADR-078).
 *
 * O upload passa pelo sanitizador antes de qualquer gravacao; o arquivo cru e
 * descartado. A leitura de paths e autenticada e restrita ao dono - a versao
 * publica do logo so existe via `?logo=u_<ULID>` no endpoint de imagem.
 */
class CustomLogoController extends Controller
{
    public function store(
        StoreCustomLogoRequest $request,
        CustomLogoSvgParser $parser,
        StoreCustomLogo $store,
    ): RedirectResponse {
        $user = $this->user($request);
        $file = $request->file('logo');

        if ($file === null || ! $file->isValid()) {
            throw ValidationException::withMessages([
                'logo' => __('validation.file', ['attribute' => __('validation.attributes.logo')]),
            ]);
        }

        $contents = (string) file_get_contents((string) $file->getRealPath());

        try {
            $parsed = $parser->parse($contents, (int) $file->getSize());
        } catch (InvalidCustomLogoException $exception) {
            throw ValidationException::withMessages([
                'logo' => __('validation.custom_logo.'.$exception->reason),
            ]);
        }

        $store($user, (string) $request->validated('name'), $parsed);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('tools.badgeforge.management.logo_created'),
        ]);

        return back();
    }

    public function show(
        Request $request,
        BadgeCustomLogo $badgeCustomLogo,
        CustomLogoRepository $repository,
    ): JsonResponse {
        $this->ensureOwner($request, $badgeCustomLogo);

        $logo = $repository->hydrate($badgeCustomLogo);

        abort_if($logo === null, 404);

        return response()
            ->json([
                'viewBox' => $logo->viewBox,
                'paths' => array_map(
                    static fn (LogoPath $path): array => [
                        'd' => $path->d,
                        'fillRule' => $path->fillRule,
                        'clipRule' => $path->clipRule,
                    ],
                    $logo->paths,
                ),
            ])
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(
        UpdateCustomLogoRequest $request,
        BadgeCustomLogo $badgeCustomLogo,
    ): RedirectResponse {
        $this->ensureOwner($request, $badgeCustomLogo);

        $badgeCustomLogo->forceFill([
            'name' => (string) $request->validated('name'),
        ])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('tools.badgeforge.management.logo_updated'),
        ]);

        return back();
    }

    public function destroy(Request $request, BadgeCustomLogo $badgeCustomLogo): RedirectResponse
    {
        $this->ensureOwner($request, $badgeCustomLogo);

        $badgeCustomLogo->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('tools.badgeforge.management.logo_deleted'),
        ]);

        return back();
    }

    private function ensureOwner(Request $request, BadgeCustomLogo $logo): void
    {
        $user = $request->user();

        // Recurso de outra conta responde 404, nunca 403: nao revela que o
        // ULID existe (mesmo padrao de `ApiTokens`).
        abort_unless(
            $user instanceof User && (int) $logo->user_id === (int) $user->getKey(),
            404,
        );
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
