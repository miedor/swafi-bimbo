<?php

namespace App\Http\Controllers;

use App\Rules\RecaptchaV3;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    public function showLogin()
    {
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
         * Login temporal para el prototipo SWAFI.
         * Más adelante puede sustituirse por autenticación real
         * contra la tabla usuarios, contraseñas cifradas y roles.
         */
        if ($request->usuario !== 'admin.swafi' || $request->password !== '12345678') {
            return back()
                ->withErrors([
                    'usuario' => 'Usuario o contraseña incorrectos.',
                ])
                ->withInput($request->only('usuario'));
        }

        Session::put('swafi_usuario', $request->usuario);
        Session::put('swafi_autenticado', true);

        return redirect()->route('dashboard');
    }

    public function logout()
    {
        Session::forget('swafi_usuario');
        Session::forget('swafi_autenticado');

        return redirect()->route('login');
    }
}
