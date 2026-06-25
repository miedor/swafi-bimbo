<?php

namespace App\Http\Controllers;

use App\Rules\RecaptchaV3;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Si ya existe sesión, no volver a mostrar login
        |--------------------------------------------------------------------------
        */

        if ($request->session()->get('swafi_autenticado')) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'usuario' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'max:120'],
            'g-recaptcha-response' => ['required', new RecaptchaV3('login')],
        ], [
            'usuario.required' => 'El usuario es obligatorio.',
            'password.required' => 'La contraseña es obligatoria.',
            'g-recaptcha-response.required' => 'No se recibió la validación reCAPTCHA.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Login temporal para prototipo SWAFI
        |--------------------------------------------------------------------------
        | Más adelante se puede sustituir por autenticación real contra la tabla
        | users, contraseña cifrada, roles y permisos.
        */

        if ($request->usuario !== 'admin.swafi' || $request->password !== '12345678') {
            return back()
                ->withErrors([
                    'usuario' => 'Usuario o contraseña incorrectos.',
                ])
                ->withInput($request->only('usuario'));
        }

        /*
        |--------------------------------------------------------------------------
        | Protección contra fijación de sesión
        |--------------------------------------------------------------------------
        | Se regenera la sesión al iniciar acceso correctamente.
        */

        $request->session()->regenerate();

        $request->session()->put('swafi_usuario', $request->usuario);
        $request->session()->put('swafi_autenticado', true);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Cierre seguro de sesión
        |--------------------------------------------------------------------------
        | Se eliminan variables de sesión y se invalida la sesión completa.
        */

        $request->session()->forget([
            'swafi_usuario',
            'swafi_autenticado',
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
