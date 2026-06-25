<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusquedaController;
use App\Http\Controllers\RegistroIndividualController;
use App\Http\Controllers\ValoresActivoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

/*
|--------------------------------------------------------------------------
| Autenticación SWAFI
|--------------------------------------------------------------------------
| Estas rutas quedan públicas porque son necesarias para iniciar sesión.
*/

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');

/*
|--------------------------------------------------------------------------
| Rutas internas protegidas
|--------------------------------------------------------------------------
| Todas estas rutas requieren que exista una sesión activa de SWAFI.
*/

Route::middleware('swafi.auth')->group(function () {

    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::view('/dashboard', 'swafi.dashboard')->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | M01 Gestión de expedientes de activo fijo
    |--------------------------------------------------------------------------
    */

    Route::get('/registro-individual', [RegistroIndividualController::class, 'create'])->name('registro-individual');
    Route::post('/registro-individual', [RegistroIndividualController::class, 'store'])->name('registro-individual.store');

    Route::view('/registro-masivo', 'swafi.registro-masivo')->name('registro-masivo');

    /*
    |--------------------------------------------------------------------------
    | M02 Control fiscal, financiero y ubicación física
    |--------------------------------------------------------------------------
    */

    Route::get('/valores-fiscales-financieros', [ValoresActivoController::class, 'index'])->name('valores');
    Route::post('/valores-fiscales-financieros', [ValoresActivoController::class, 'store'])->name('valores.store');
    Route::delete('/valores-fiscales-financieros/{valor}', [ValoresActivoController::class, 'destroy'])->name('valores.destroy');

    Route::view('/ubicacion-inventario', 'swafi.ubicacion')->name('ubicacion');

    /*
    |--------------------------------------------------------------------------
    | M03 Consultas, reportes y seguimiento
    |--------------------------------------------------------------------------
    */

    Route::get('/busqueda-avanzada', [BusquedaController::class, 'index'])->name('busqueda');
    Route::get('/detalle-expediente/{expediente?}', [BusquedaController::class, 'show'])->name('expediente');

    Route::view('/reportes', 'swafi.reportes')->name('reportes');

    /*
    |--------------------------------------------------------------------------
    | M04 Administración y seguridad del sistema
    |--------------------------------------------------------------------------
    */

    Route::view('/catalogos', 'swafi.catalogos')->name('catalogos');
    Route::view('/seguridad-acceso', 'swafi.seguridad')->name('seguridad');
});
