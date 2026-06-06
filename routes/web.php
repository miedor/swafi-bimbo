<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RegistroIndividualController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

/*
|--------------------------------------------------------------------------
| Autenticación SWAFI
|--------------------------------------------------------------------------
| Se sustituye el acceso visual directo por un flujo controlado:
| - GET /login: muestra el formulario de acceso.
| - POST /login: valida usuario, contraseña y reCAPTCHA v3.
| - GET /logout: limpia sesión y regresa al login.
*/

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Páginas principales SWAFI
|--------------------------------------------------------------------------
*/

Route::view('/dashboard', 'swafi.dashboard')->name('dashboard');

Route::get('/registro-individual', [RegistroIndividualController::class, 'create'])->name('registro-individual');
Route::post('/registro-individual', [RegistroIndividualController::class, 'store'])->name('registro-individual.store');

Route::view('/registro-masivo', 'swafi.registro-masivo')->name('registro-masivo');
Route::view('/valores-fiscales-financieros', 'swafi.valores')->name('valores');
Route::view('/ubicacion-inventario', 'swafi.ubicacion')->name('ubicacion');
Route::view('/busqueda-avanzada', 'swafi.busqueda')->name('busqueda');
Route::view('/reportes', 'swafi.reportes')->name('reportes');
Route::view('/catalogos', 'swafi.catalogos')->name('catalogos');
Route::view('/seguridad-acceso', 'swafi.seguridad')->name('seguridad');
Route::view('/detalle-expediente', 'swafi.expediente')->name('expediente');
