<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::view('/login', 'auth.login')->name('login');
Route::view('/dashboard', 'swafi.dashboard')->name('dashboard');
Route::view('/registro-individual', 'swafi.registro-individual')->name('registro-individual');
Route::view('/registro-masivo', 'swafi.registro-masivo')->name('registro-masivo');
Route::view('/valores-fiscales-financieros', 'swafi.valores')->name('valores');
Route::view('/ubicacion-inventario', 'swafi.ubicacion')->name('ubicacion');
Route::view('/busqueda-avanzada', 'swafi.busqueda')->name('busqueda');
Route::view('/reportes', 'swafi.reportes')->name('reportes');
Route::view('/catalogos', 'swafi.catalogos')->name('catalogos');
Route::view('/seguridad-acceso', 'swafi.seguridad')->name('seguridad');
Route::view('/detalle-expediente', 'swafi.expediente')->name('expediente');
