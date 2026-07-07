<?php

namespace App\Http\Controllers;

use App\Mail\SwafiPasswordResetMail;
use App\Models\User;
use App\Rules\RecaptchaV3;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    private int $tokenMinutes = 60;

    public function showForgotForm(Request $request)
    {
        if ($request->session()->get('swafi_autenticado')) {
            return redirect()->route('dashboard');
        }

        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'g-recaptcha-response' => ['required', new RecaptchaV3('forgot_password')],
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Debes capturar un correo electrónico válido.',
            'g-recaptcha-response.required' => 'No se recibió la validación reCAPTCHA.',
        ]);

        $email = mb_strtolower(trim($request->input('email')));

        $user = User::query()
            ->where('email', $email)
            ->first();

        /*
         * Por seguridad, la respuesta es genérica. Así evitamos confirmar
         * públicamente si un correo existe o no dentro de SWAFI.
         */
        if (!$user || !$this->isUserActive($user)) {
            $this->registrarBitacoraPassword(
                userId: $user?->id,
                accion: 'RECUPERACION_NO_ENVIADA',
                detalle: [
                    'email_solicitado' => $email,
                    'motivo' => 'Usuario inexistente o inactivo.',
                ]
            );

            return back()->with('status', 'Si el correo capturado está registrado y activo, recibirás un enlace para restablecer tu contraseña.');
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]);

        try {
            Mail::to($user->email)->send(
                new SwafiPasswordResetMail(
                    resetUrl: $resetUrl,
                    userName: $user->name,
                    minutes: $this->tokenMinutes
                )
            );

            $this->registrarBitacoraPassword(
                userId: $user->id,
                accion: 'RECUPERACION_ENVIADA',
                detalle: [
                    'email' => $user->email,
                    'vigencia_minutos' => $this->tokenMinutes,
                ]
            );
        } catch (\Throwable $exception) {
            $this->registrarBitacoraPassword(
                userId: $user->id,
                accion: 'RECUPERACION_ERROR_ENVIO',
                detalle: [
                    'email' => $user->email,
                    'error' => $exception->getMessage(),
                ]
            );

            return back()->withErrors([
                'email' => 'No fue posible enviar el correo de recuperación. Revisa la configuración de correo del servidor.',
            ]);
        }

        return back()->with('status', 'Si el correo capturado está registrado y activo, recibirás un enlace para restablecer tu contraseña.');
    }

    public function showResetForm(Request $request, string $token)
    {
        if ($request->session()->get('swafi_autenticado')) {
            return redirect()->route('dashboard');
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:120', 'confirmed'],
            'g-recaptcha-response' => ['required', new RecaptchaV3('reset_password')],
        ], [
            'token.required' => 'El token de recuperación es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'password.required' => 'La nueva contraseña es obligatoria.',
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'g-recaptcha-response.required' => 'No se recibió la validación reCAPTCHA.',
        ]);

        $email = mb_strtolower(trim($request->input('email')));
        $token = (string) $request->input('token');

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$record) {
            return back()
                ->withErrors(['email' => 'El enlace de recuperación no es válido o ya fue utilizado.'])
                ->withInput($request->only('email'));
        }

        $createdAt = $record->created_at
            ? Carbon::parse($record->created_at)
            : null;

        if (!$createdAt || $createdAt->lt(now()->subMinutes($this->tokenMinutes))) {
            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->delete();

            return back()
                ->withErrors(['email' => 'El enlace de recuperación expiró. Solicita uno nuevo.'])
                ->withInput($request->only('email'));
        }

        if (!Hash::check($token, $record->token)) {
            $this->registrarBitacoraPassword(
                userId: null,
                accion: 'RECUPERACION_TOKEN_INVALIDO',
                detalle: [
                    'email_solicitado' => $email,
                ]
            );

            return back()
                ->withErrors(['email' => 'El enlace de recuperación no es válido.'])
                ->withInput($request->only('email'));
        }

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (!$user || !$this->isUserActive($user)) {
            return back()
                ->withErrors(['email' => 'El usuario no existe o se encuentra inactivo.'])
                ->withInput($request->only('email'));
        }

        DB::transaction(function () use ($user, $request, $email) {
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'password' => Hash::make($request->input('password')),
                    'updated_at' => now(),
                ]);

            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->delete();

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->delete();
            }

            $this->registrarBitacoraPassword(
                userId: $user->id,
                accion: 'CONTRASENA_RESTABLECIDA',
                detalle: [
                    'email' => $user->email,
                    'usuario' => $user->usuario ?? null,
                ]
            );
        });

        return redirect()
            ->route('login')
            ->with('status', 'Tu contraseña fue actualizada correctamente. Ya puedes iniciar sesión.');
    }

    private function isUserActive(User $user): bool
    {
        if (!Schema::hasColumn('users', 'estatus')) {
            return true;
        }

        return ($user->estatus ?? 'activo') === 'activo';
    }

    private function registrarBitacoraPassword(?int $userId, string $accion, array $detalle): void
    {
        try {
            if (!Schema::hasTable('bitacora_auditoria')) {
                return;
            }

            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => null,
                'user_id' => $userId,
                'modulo' => 'M04 Administración y seguridad del sistema',
                'accion' => $accion,
                'tabla_afectada' => 'users',
                'registro_clave' => $userId ? (string) $userId : null,
                'antes' => null,
                'despues' => json_encode($detalle, JSON_UNESCAPED_UNICODE),
                'ip' => request()->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            // La recuperación de contraseña no debe bloquearse por error de bitácora.
        }
    }
}
