<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect('/login'));
Route::get('/login', fn() => view('auth.login'))->name('login');
Route::get('/swafi', fn() => view('swafi.dashboard'))->name('swafi.dashboard');
Route::get('/swafi/registro-individual', fn() => view('swafi.registro-individual'))->name('swafi.registro-individual');
Route::get('/swafi/registro-masivo', fn() => view('swafi.registro-masivo'))->name('swafi.registro-masivo');
Route::get('/swafi/valores', fn() => view('swafi.valores'))->name('swafi.valores');
Route::get('/swafi/ubicacion', fn() => view('swafi.ubicacion'))->name('swafi.ubicacion');
Route::get('/swafi/busqueda', fn() => view('swafi.busqueda'))->name('swafi.busqueda');
Route::get('/swafi/reportes', fn() => view('swafi.reportes'))->name('swafi.reportes');
Route::get('/swafi/catalogos', fn() => view('swafi.catalogos'))->name('swafi.catalogos');
Route::get('/swafi/seguridad', fn() => view('swafi.seguridad'))->name('swafi.seguridad');
Route::get('/swafi/expediente', fn() => view('swafi.expediente'))->name('swafi.expediente');
