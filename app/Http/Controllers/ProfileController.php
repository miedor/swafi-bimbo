<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
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

        $payload = [
            'name' => trim($validated['name']),
            'updated_at' => now(),
        ];

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $extension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';

            $path = $file->storeAs(
                'profile_avatars/user_' . $user->id,
                'avatar_' . now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $extension,
                'local'
            );

            if (!empty($user->avatar_path) && Storage::disk('local')->exists($user->avatar_path)) {
                Storage::disk('local')->delete($user->avatar_path);
            }

            $payload['avatar_path'] = $path;
            $payload['avatar_mime'] = $file->getMimeType() ?: 'image/' . $extension;
        }

        DB::table('users')
            ->where('id', $user->id)
            ->update($payload);

        $updatedUser = DB::table('users')
            ->where('id', $user->id)
            ->first();

        $request->session()->put('swafi_nombre', $updatedUser->name);
        $request->session()->put('swafi_avatar_path', $updatedUser->avatar_path ?? null);
        $request->session()->put('swafi_avatar_version', now()->timestamp);

        $this->registrarBitacora(
            userId: (int) $user->id,
            accion: 'PERFIL_ACTUALIZADO',
            antes: $antes,
            despues: [
                'name' => $updatedUser->name,
                'avatar_path' => $updatedUser->avatar_path,
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
        abort_if(!Storage::disk('local')->exists($user->avatar_path), 404);

        return Storage::disk('local')->response($user->avatar_path, 'avatar-swafi', [
            'Content-Type' => $user->avatar_mime ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=120',
        ]);
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);

        abort_if(!$user, 404, 'El usuario de la sesión no existe.');

        $antes = (array) $user;

        if (!empty($user->avatar_path) && Storage::disk('local')->exists($user->avatar_path)) {
            Storage::disk('local')->delete($user->avatar_path);
        }

        DB::table('users')
            ->where('id', $user->id)
            ->update([
                'avatar_path' => null,
                'avatar_mime' => null,
                'updated_at' => now(),
            ]);

        $request->session()->forget(['swafi_avatar_path']);
        $request->session()->put('swafi_avatar_version', now()->timestamp);

        $this->registrarBitacora(
            userId: (int) $user->id,
            accion: 'PERFIL_AVATAR_ELIMINADO',
            antes: $antes,
            despues: ['avatar_path' => null]
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
            // La actualización del perfil no debe fallar por bitácora.
        }
    }
}
