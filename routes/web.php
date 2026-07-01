<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusquedaController;
use App\Http\Controllers\RegistroIndividualController;
use App\Http\Controllers\UbicacionInventarioController;
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

    Route::get('/valores-fiscales-financieros/plantilla-csv', [ValoresActivoController::class, 'plantillaCsv'])->name('valores.plantilla');
    Route::post('/valores-fiscales-financieros/importar', [ValoresActivoController::class, 'importar'])->name('valores.importar');

    Route::delete('/valores-fiscales-financieros/{valor}', [ValoresActivoController::class, 'destroy'])
        ->whereNumber('valor')
        ->name('valores.destroy');

    Route::get('/ubicacion-inventario', [UbicacionInventarioController::class, 'index'])->name('ubicacion');
    Route::post('/ubicacion-inventario/movimiento', [UbicacionInventarioController::class, 'storeMovimiento'])->name('ubicacion.movimiento');
    Route::post('/ubicacion-inventario/inventario', [UbicacionInventarioController::class, 'storeInventario'])->name('ubicacion.inventario');

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
