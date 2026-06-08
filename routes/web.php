<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusquedaController;
use App\Http\Controllers\RegistroIndividualController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

/*
|--------------------------------------------------------------------------
| Autenticación SWAFI
|--------------------------------------------------------------------------
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

Route::get('/busqueda-avanzada', [BusquedaController::class, 'index'])->name('busqueda');
Route::get('/detalle-expediente/{expediente?}', [BusquedaController::class, 'show'])->name('expediente');

Route::view('/reportes', 'swafi.reportes')->name('reportes');
Route::view('/catalogos', 'swafi.catalogos')->name('catalogos');
Route::view('/seguridad-acceso', 'swafi.seguridad')->name('seguridad');
