<?php

namespace App\Http\Controllers;

use App\Services\SwafiStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function __construct(private readonly SwafiStorageService $storage)
    {
    }

    public function show(Request $request)
    {
        $user = $this->currentUser($request);

        abort_if(!$user, 404, 'El usuario de la sesión no existe.');

        $roles = DB::table('roles as r')
            ->join('role_user as ru', 'ru.role_id', '=', 'r.id')
            ->where('ru.user_id', $user->id)
            ->where('r.activo', 1)
            ->orderBy('r.nombre')
            ->pluck('r.nombre')
            ->all();

        return view('swafi.perfil', [
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);

        abort_if(!$user, 404, 'El usuario de la sesión no existe.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'name.required' => 'El nombre del perfil es obligatorio.',
            'avatar.mimes' => 'La imagen debe ser JPG, JPEG, PNG o WEBP.',
            'avatar.max' => 'La imagen no debe superar 2 MB.',
        ]);

        $antes = (array) $user;
        $newStoredFile = null;
        $oldDisk = $user->avatar_disk ?? null;
        $oldPath = $user->avatar_path ?? null;

        try {
            $payload = [
                'name' => trim($validated['name']),
                'updated_at' => now(),
            ];

            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
                $extension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';

                $storedName = 'avatar_'
                    . now()->format('Ymd_His')
                    . '_'
                    . Str::random(10)
                    . '.'
                    . $extension;

                $newStoredFile = $this->storage->storeUploadedFile(
                    file: $file,
                    directory: 'swafi/avatars/user_' . $user->id,
                    storedName: $storedName
                );

                $payload['avatar_path'] = $newStoredFile['path'];
                $payload['avatar_disk'] = $newStoredFile['disk'];
                $payload['avatar_mime'] = $newStoredFile['mime_type'];
            }

            DB::transaction(function () use ($user, $payload): void {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update($payload);
            });
        } catch (\Throwable $exception) {
            if ($newStoredFile) {
                $this->storage->delete($newStoredFile['disk'], $newStoredFile['path']);
            }

            throw $exception;
        }

        if (
            $newStoredFile
            && $oldPath
            && ($oldPath !== $newStoredFile['path'] || $oldDisk !== $newStoredFile['disk'])
        ) {
            $this->storage->delete($oldDisk, $oldPath);
        }

        $updatedUser = DB::table('users')
            ->where('id', $user->id)
            ->first();

        $request->session()->put('swafi_nombre', $updatedUser->name);
        $request->session()->put('swafi_avatar_path', $updatedUser->avatar_path ?? null);
        $request->session()->put('swafi_avatar_disk', $updatedUser->avatar_disk ?? null);
        $request->session()->put('swafi_avatar_version', now()->timestamp);

        $this->registrarBitacora(
            userId: (int) $user->id,
            accion: 'PERFIL_ACTUALIZADO',
            antes: $antes,
            despues: [
                'name' => $updatedUser->name,
                'avatar_path' => $updatedUser->avatar_path,
                'avatar_disk' => $updatedUser->avatar_disk,
            ]
        );

        return redirect()
            ->route('perfil')
            ->with('success', 'Tu perfil SWAFI fue actualizado correctamente.');
    }

    public function avatar(Request $request): StreamedResponse
    {
        $user = $this->currentUser($request);

        abort_if(!$user || empty($user->avatar_path), 404);

        $validation = $this->storage->validate(
            $user->avatar_disk ?? null,
            (string) $user->avatar_path
        );

        abort_if(!$validation['ok'], 404);

        return $this->storage->inlineResponse(
            disk: $validation['disk'],
            path: $validation['path'],
            downloadName: 'avatar-swafi.' . $this->avatarExtension((string) ($user->avatar_mime ?: $validation['mime_type'])),
            mimeType: $user->avatar_mime ?: $validation['mime_type'],
            headers: ['Cache-Control' => 'private, max-age=120']
        );
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);

        abort_if(!$user, 404, 'El usuario de la sesión no existe.');

        $antes = (array) $user;
        $oldDisk = $user->avatar_disk ?? null;
        $oldPath = $user->avatar_path ?? null;

        DB::transaction(function () use ($user): void {
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'avatar_path' => null,
                    'avatar_disk' => null,
                    'avatar_mime' => null,
                    'updated_at' => now(),
                ]);
        });

        if ($oldPath) {
            $this->storage->delete($oldDisk, $oldPath);
        }

        $request->session()->forget([
            'swafi_avatar_path',
            'swafi_avatar_disk',
        ]);
        $request->session()->put('swafi_avatar_version', now()->timestamp);

        $this->registrarBitacora(
            userId: (int) $user->id,
            accion: 'PERFIL_AVATAR_ELIMINADO',
            antes: $antes,
            despues: [
                'avatar_path' => null,
                'avatar_disk' => null,
            ]
        );

        return redirect()
            ->route('perfil')
            ->with('success', 'La imagen del perfil fue eliminada correctamente.');
    }

    private function currentUser(Request $request): ?object
    {
        $userId = (int) $request->session()->get('swafi_user_id');

        if ($userId <= 0) {
            return null;
        }

        return DB::table('users')
            ->where('id', $userId)
            ->first();
    }

    private function avatarExtension(string $mimeType): string
    {
        return match (strtolower($mimeType)) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/jpeg', 'image/jpg' => 'jpg',
            default => 'jpg',
        };
    }

    private function registrarBitacora(int $userId, string $accion, ?array $antes, ?array $despues): void
    {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => $userId,
                'modulo' => 'M04 Administración y seguridad del sistema',
                'accion' => $accion,
                'tabla_afectada' => 'users',
                'registro_clave' => (string) $userId,
                'antes' => $antes ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
                'despues' => $despues ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
                'ip' => request()->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            app(\App\Services\SafeExceptionReporter::class)->warning(
                $exception,
                'profile_audit_write',
                [
                    'user_id' => auth()->id(),
                    'route_name' => request()->route()?->getName(),
                ]
            );
        }
    }
}
