<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfilePhotoController extends Controller
{
    /**
     * Diretorio de armazenamento das fotos de perfil do usuario.
     */
    public static function storagePath(?string $filename = null): string
    {
        $base = storage_path('app/data/user_profile_image');

        if (! File::isDirectory($base)) {
            File::makeDirectory($base, 0755, true, true);
        }

        return $filename !== null ? $base.DIRECTORY_SEPARATOR.$filename : $base;
    }

    /**
     * Servir a foto de perfil publicamente via UUID.
     * Rota publica: GET /users/profile/photo/{uuid}
     */
    public function show(string $uuid): BinaryFileResponse
    {
        if (! Str::isUuid($uuid)) {
            abort(404);
        }

        $path = self::storagePath($uuid);

        if (! File::exists($path)) {
            abort(404);
        }

        $mime = File::mimeType($path) ?: 'image/jpeg';

        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Fazer upload e atualizar a foto de perfil.
     * Rota: POST /settings/profile/photo
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $photo = $request->file('photo');

        if (! $photo || ! $photo->isValid()) {
            return back()->withErrors(['photo' => __('settings.profile.photo_error')]);
        }

        $uuid = (string) Str::uuid();

        // Remove foto anterior se houver
        if ($user->photo_uuid) {
            $oldPath = self::storagePath($user->photo_uuid);
            if (File::exists($oldPath)) {
                File::delete($oldPath);
            }
        }

        $photo->move(self::storagePath(), $uuid);

        $user->forceFill([
            'photo_uuid' => $uuid,
        ])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('settings.profile.photo_updated'),
        ]);

        return back();
    }

    /**
     * Remover a foto de perfil.
     * Rota: DELETE /settings/profile/photo
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        if ($user->photo_uuid) {
            $oldPath = self::storagePath($user->photo_uuid);
            if (File::exists($oldPath)) {
                File::delete($oldPath);
            }

            $user->forceFill([
                'photo_uuid' => null,
            ])->save();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('settings.profile.photo_deleted'),
        ]);

        return back();
    }
}
