<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusquedaController;
use App\Http\Controllers\CatalogosController;
use App\Http\Controllers\DocumentoExpedienteController;
use App\Http\Controllers\ExpedienteGestionController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\RegistroIndividualController;
use App\Http\Controllers\RegistroMasivoController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\SeguridadController;
use App\Http\Controllers\UbicacionInventarioController;
use App\Http\Controllers\ValoresActivoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');

Route::get('/olvide-contrasena', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
Route::post('/olvide-contrasena', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
Route::get('/restablecer-contrasena/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/restablecer-contrasena', [PasswordResetController::class, 'resetPassword'])->name('password.update');

Route::middleware('swafi.auth')->group(function () {

    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::view('/dashboard', 'swafi.dashboard')->name('dashboard');

    Route::get('/registro-individual', [RegistroIndividualController::class, 'create'])->name('registro-individual');
    Route::post('/registro-individual', [RegistroIndividualController::class, 'store'])->name('registro-individual.store');

    Route::get('/registro-masivo', [RegistroMasivoController::class, 'index'])->name('registro-masivo');
    Route::post('/registro-masivo/importar', [RegistroMasivoController::class, 'importar'])->name('registro-masivo.importar');
    Route::get('/registro-masivo/plantilla-csv', [RegistroMasivoController::class, 'plantillaCsv'])->name('registro-masivo.plantilla');

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

    Route::get('/busqueda-avanzada', [BusquedaController::class, 'index'])->name('busqueda');

    Route::get('/documentos-expediente/{documento}/ver', [DocumentoExpedienteController::class, 'show'])
        ->whereNumber('documento')
        ->name('documentos.ver');
    Route::post('/expedientes/{expediente}/documentos', [DocumentoExpedienteController::class, 'store'])
    ->whereNumber('expediente')
    ->name('documentos.store');

    Route::delete('/documentos-expediente/{documento}', [DocumentoExpedienteController::class, 'destroy'])
    ->whereNumber('documento')
    ->name('documentos.eliminar');

    Route::get('/documentos-expediente/{documento}/descargar', [DocumentoExpedienteController::class, 'download'])
        ->whereNumber('documento')
        ->name('documentos.descargar');

    Route::get('/expedientes/{expediente}/documentos/descargar', [DocumentoExpedienteController::class, 'downloadAll'])
        ->whereNumber('expediente')
        ->name('documentos.descargar-todos');


    Route::get('/expedientes/{expediente}/editar', [ExpedienteGestionController::class, 'edit'])
        ->whereNumber('expediente')
        ->name('expedientes.editar');

    Route::put('/expedientes/{expediente}', [ExpedienteGestionController::class, 'update'])
        ->whereNumber('expediente')
        ->name('expedientes.actualizar');

    Route::delete('/expedientes/{expediente}', [ExpedienteGestionController::class, 'destroy'])
        ->whereNumber('expediente')
        ->name('expedientes.eliminar');

    Route::get('/detalle-expediente/{expediente?}', [BusquedaController::class, 'show'])->name('expediente');

    Route::get('/reportes', [ReportesController::class, 'index'])->name('reportes');

    Route::get('/catalogos', [CatalogosController::class, 'index'])->name('catalogos');
    Route::post('/catalogos', [CatalogosController::class, 'store'])->name('catalogos.store');
    Route::post('/catalogos/importar', [CatalogosController::class, 'importar'])->name('catalogos.importar');
    Route::get('/catalogos/plantilla-csv', [CatalogosController::class, 'plantillaCsv'])->name('catalogos.plantilla');

    Route::delete('/catalogos/{catalogo}/{id}', [CatalogosController::class, 'destroy'])
        ->whereNumber('id')
        ->name('catalogos.destroy');

    Route::get('/seguridad-acceso', [SeguridadController::class, 'index'])->name('seguridad');

    Route::post('/seguridad-acceso/usuarios', [SeguridadController::class, 'storeUser'])->name('seguridad.usuarios.store');
    Route::delete('/seguridad-acceso/usuarios/{user}', [SeguridadController::class, 'destroyUser'])
        ->whereNumber('user')
        ->name('seguridad.usuarios.destroy');

    Route::post('/seguridad-acceso/roles', [SeguridadController::class, 'storeRole'])->name('seguridad.roles.store');
    Route::delete('/seguridad-acceso/roles/{role}', [SeguridadController::class, 'destroyRole'])
        ->whereNumber('role')
        ->name('seguridad.roles.destroy');

    Route::post('/seguridad-acceso/permisos', [SeguridadController::class, 'storePermission'])->name('seguridad.permisos.store');
});
