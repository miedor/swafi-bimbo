<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusquedaController;
use App\Http\Controllers\BusquedaGuardadaController;
use App\Http\Controllers\CatalogosController;
use App\Http\Controllers\CfdiValidationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoExpedienteController;
use App\Http\Controllers\EtiquetaActivoController;
use App\Http\Controllers\ExpedienteGestionController;
use App\Http\Controllers\ExpedienteObservacionController;
use App\Http\Controllers\InventarioEvidenciaController;
use App\Http\Controllers\InventoryPeriodController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegistroIndividualController;
use App\Http\Controllers\RegistroMasivoController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\ReporteGuardadoController;
use App\Http\Controllers\ReporteProgramadoController;
use App\Http\Controllers\SeguridadController;
use App\Http\Controllers\TransferApprovalController;
use App\Http\Controllers\UbicacionInventarioController;
use App\Http\Controllers\ValorActivoExportController;
use App\Http\Controllers\ValorActivoHistoryController;
use App\Http\Controllers\ValoresActivoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/olvide-contrasena', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
Route::post('/olvide-contrasena', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
Route::get('/restablecer-contrasena/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/restablecer-contrasena', [PasswordResetController::class, 'resetPassword'])->name('password.update');

Route::middleware('swafi.auth')->group(function () {

    Route::post('/sesion/actividad', [AuthController::class, 'heartbeat'])->name('session.heartbeat');

    Route::get('/perfil', [ProfileController::class, 'show'])->name('perfil');
    Route::post('/perfil', [ProfileController::class, 'update'])->name('perfil.update');
    Route::get('/perfil/avatar', [ProfileController::class, 'avatar'])->name('perfil.avatar');
    Route::delete('/perfil/avatar', [ProfileController::class, 'destroyAvatar'])->name('perfil.avatar.destroy');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/registro-individual', [RegistroIndividualController::class, 'create'])->name('registro-individual');
    Route::get('/registro-individual/activo-existente', [RegistroIndividualController::class, 'showExistingAsset'])
        ->name('registro-individual.activo');
    Route::post('/registro-individual', [RegistroIndividualController::class, 'store'])->name('registro-individual.store');

    Route::get('/registro-masivo', [RegistroMasivoController::class, 'index'])->name('registro-masivo');
    Route::post('/registro-masivo/importar', [RegistroMasivoController::class, 'importar'])->name('registro-masivo.importar');

    Route::post('/registro-masivo/lotes/{lote}/aplicar', [RegistroMasivoController::class, 'aplicar'])
        ->whereUuid('lote')
        ->name('registro-masivo.aplicar');

    Route::delete('/registro-masivo/lotes/{lote}', [RegistroMasivoController::class, 'cancelar'])
        ->whereUuid('lote')
        ->name('registro-masivo.cancelar');

    Route::patch('/registro-masivo/lotes/{lote}/revertir', [RegistroMasivoController::class, 'revertir'])
        ->whereUuid('lote')
        ->name('registro-masivo.revertir');

    Route::get('/registro-masivo/lotes/{lote}/incidencias.xlsx', [RegistroMasivoController::class, 'exportarIncidencias'])
        ->whereUuid('lote')
        ->name('registro-masivo.incidencias');

    Route::get('/registro-masivo/lotes/{lote}/incidencias.csv', [RegistroMasivoController::class, 'exportarIncidenciasCsv'])
        ->whereUuid('lote')
        ->name('registro-masivo.incidencias-csv');

    Route::get('/registro-masivo/plantilla-csv', [RegistroMasivoController::class, 'plantillaCsv'])->name('registro-masivo.plantilla');

    Route::get('/valores-fiscales-financieros', [ValoresActivoController::class, 'index'])->name('valores');
    Route::get('/valores-fiscales-financieros/{numeroActivo}/historial', [ValorActivoHistoryController::class, 'index'])
        ->where('numeroActivo', '[A-Za-z0-9._-]+')
        ->name('valores.historial');

    Route::get('/valores-fiscales-financieros/{numeroActivo}/exportar/{formato}', [ValorActivoExportController::class, 'download'])
        ->where('numeroActivo', '[A-Za-z0-9._-]+')
        ->where('formato', 'xlsx|pdf')
        ->name('valores.exportar-ficha');

    Route::post('/valores-fiscales-financieros', [ValoresActivoController::class, 'store'])->name('valores.store');
    Route::get('/valores-fiscales-financieros/plantilla-csv', [ValoresActivoController::class, 'plantillaCsv'])->name('valores.plantilla');
    Route::post('/valores-fiscales-financieros/importar', [ValoresActivoController::class, 'importar'])->name('valores.importar');

    Route::delete('/valores-fiscales-financieros/{valor}', [ValoresActivoController::class, 'destroy'])
        ->whereNumber('valor')
        ->name('valores.destroy');

    Route::get('/activos/{activo}/etiqueta', [EtiquetaActivoController::class, 'show'])
        ->name('activos.etiqueta');

    Route::post('/activos/{activo}/etiqueta/auditar', [EtiquetaActivoController::class, 'audit'])
        ->name('activos.etiqueta.auditar');

    Route::get('/ubicacion-inventario', [UbicacionInventarioController::class, 'index'])->name('ubicacion');
    Route::post('/ubicacion-inventario/movimiento', [UbicacionInventarioController::class, 'storeMovimiento'])->name('ubicacion.movimiento');
    Route::post('/ubicacion-inventario/inventario', [UbicacionInventarioController::class, 'storeInventario'])->name('ubicacion.inventario');

    Route::patch('/ubicacion-inventario/traslados/{solicitud}/aprobar', [TransferApprovalController::class, 'approve'])
        ->whereNumber('solicitud')
        ->name('ubicacion.traslados.aprobar');

    Route::patch('/ubicacion-inventario/traslados/{solicitud}/rechazar', [TransferApprovalController::class, 'reject'])
        ->whereNumber('solicitud')
        ->name('ubicacion.traslados.rechazar');

    Route::post('/ubicacion-inventario/traslados/{solicitud}/notificar', [TransferApprovalController::class, 'resendNotification'])
        ->whereNumber('solicitud')
        ->name('ubicacion.traslados.notificar');

    Route::post('/ubicacion-inventario/periodos', [InventoryPeriodController::class, 'store'])
        ->name('ubicacion.periodos.store');

    Route::patch('/ubicacion-inventario/periodos/{periodo}/bloquear', [InventoryPeriodController::class, 'block'])
        ->whereNumber('periodo')
        ->name('ubicacion.periodos.bloquear');

    Route::patch('/ubicacion-inventario/periodos/{periodo}/desbloquear', [InventoryPeriodController::class, 'unblock'])
        ->whereNumber('periodo')
        ->name('ubicacion.periodos.desbloquear');

    Route::get('/inventario-evidencias/{evidencia}/ver', [InventarioEvidenciaController::class, 'show'])
        ->whereNumber('evidencia')
        ->name('inventario-evidencias.ver');

    Route::get('/inventario-evidencias/{evidencia}/descargar', [InventarioEvidenciaController::class, 'download'])
        ->whereNumber('evidencia')
        ->name('inventario-evidencias.descargar');

    Route::delete('/inventario-evidencias/{evidencia}', [InventarioEvidenciaController::class, 'destroy'])
        ->whereNumber('evidencia')
        ->name('inventario-evidencias.eliminar');

    Route::get('/busqueda-avanzada', [BusquedaController::class, 'index'])->name('busqueda');

    Route::post('/busquedas-guardadas', [BusquedaGuardadaController::class, 'store'])
        ->name('busquedas-guardadas.store');

    Route::get('/busquedas-guardadas/{busqueda}/aplicar', [BusquedaGuardadaController::class, 'apply'])
        ->whereNumber('busqueda')
        ->name('busquedas-guardadas.apply');

    Route::delete('/busquedas-guardadas/{busqueda}', [BusquedaGuardadaController::class, 'destroy'])
        ->whereNumber('busqueda')
        ->name('busquedas-guardadas.destroy');

    Route::get('/documentos-expediente/{documento}/ver', [DocumentoExpedienteController::class, 'show'])
        ->whereNumber('documento')
        ->name('documentos.ver');

    Route::post('/expedientes/{expediente}/documentos', [DocumentoExpedienteController::class, 'store'])
        ->whereNumber('expediente')
        ->name('documentos.store');

    Route::delete('/documentos-expediente/{documento}', [DocumentoExpedienteController::class, 'destroy'])
        ->whereNumber('documento')
        ->name('documentos.eliminar');

    Route::post('/expedientes/{expediente}/cfdi/revalidar', [CfdiValidationController::class, 'revalidate'])
        ->whereNumber('expediente')
        ->name('cfdi.revalidar');

    Route::get('/documentos-expediente/{documento}/descargar', [DocumentoExpedienteController::class, 'download'])
        ->whereNumber('documento')
        ->name('documentos.descargar');

    Route::get('/expedientes/{expediente}/documentos/descargar', [DocumentoExpedienteController::class, 'downloadAll'])
        ->whereNumber('expediente')
        ->name('documentos.descargar-todos');

    Route::post('/expedientes/{expediente}/observaciones', [ExpedienteObservacionController::class, 'store'])
        ->whereNumber('expediente')
        ->name('observaciones.store');

    Route::patch('/observaciones-expediente/{observacion}/fecha-compromiso', [ExpedienteObservacionController::class, 'updateDeadline'])
        ->whereNumber('observacion')
        ->name('observaciones.actualizar-fecha');

    Route::patch('/observaciones-expediente/{observacion}/tomar', [ExpedienteObservacionController::class, 'tomar'])
        ->whereNumber('observacion')
        ->name('observaciones.tomar');

    Route::patch('/observaciones-expediente/{observacion}/atender', [ExpedienteObservacionController::class, 'atender'])
        ->whereNumber('observacion')
        ->name('observaciones.atender');

    Route::patch('/observaciones-expediente/{observacion}/validar', [ExpedienteObservacionController::class, 'validar'])
        ->whereNumber('observacion')
        ->name('observaciones.validar');

    Route::delete('/observaciones-expediente/{observacion}/cancelar', [ExpedienteObservacionController::class, 'cancelar'])
        ->whereNumber('observacion')
        ->name('observaciones.cancelar');

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

    Route::post('/reportes-guardados', [ReporteGuardadoController::class, 'store'])
        ->name('reportes-guardados.store');

    Route::get('/reportes-guardados/{reporte}/aplicar', [ReporteGuardadoController::class, 'apply'])
        ->whereNumber('reporte')
        ->name('reportes-guardados.apply');

    Route::delete('/reportes-guardados/{reporte}', [ReporteGuardadoController::class, 'destroy'])
        ->whereNumber('reporte')
        ->name('reportes-guardados.destroy');


    Route::post('/reportes-programados', [ReporteProgramadoController::class, 'store'])
        ->name('reportes-programados.store');

    Route::patch('/reportes-programados/{programacion}/estado', [ReporteProgramadoController::class, 'toggle'])
        ->whereNumber('programacion')
        ->name('reportes-programados.toggle');

    Route::delete('/reportes-programados/{programacion}', [ReporteProgramadoController::class, 'destroy'])
        ->whereNumber('programacion')
        ->name('reportes-programados.destroy');

    Route::get('/catalogos', [CatalogosController::class, 'index'])->name('catalogos');
    Route::post('/catalogos', [CatalogosController::class, 'store'])->name('catalogos.store');
    Route::post('/catalogos/importar', [CatalogosController::class, 'importar'])->name('catalogos.importar');

    Route::post('/catalogos/importaciones/{lote}/aplicar', [CatalogosController::class, 'aplicarImportacion'])
        ->whereUuid('lote')
        ->name('catalogos.importaciones.aplicar');

    Route::delete('/catalogos/importaciones/{lote}', [CatalogosController::class, 'cancelarImportacion'])
        ->whereUuid('lote')
        ->name('catalogos.importaciones.cancelar');

    Route::get('/catalogos/importaciones/{lote}/incidencias.xlsx', [CatalogosController::class, 'exportarIncidenciasXlsx'])
        ->whereUuid('lote')
        ->name('catalogos.importaciones.incidencias-xlsx');

    Route::get('/catalogos/importaciones/{lote}/incidencias.csv', [CatalogosController::class, 'exportarIncidenciasCsv'])
        ->whereUuid('lote')
        ->name('catalogos.importaciones.incidencias-csv');

    Route::get('/catalogos/plantilla-csv', [CatalogosController::class, 'plantillaCsv'])->name('catalogos.plantilla');

    Route::delete('/catalogos/{catalogo}/{id}', [CatalogosController::class, 'destroy'])
        ->whereNumber('id')
        ->name('catalogos.destroy');

    Route::patch('/catalogos/{catalogo}/{id}/activar', [CatalogosController::class, 'activate'])
        ->whereNumber('id')
        ->name('catalogos.activate');

    Route::get('/seguridad-acceso', [SeguridadController::class, 'index'])->name('seguridad');

    Route::post('/seguridad-acceso/usuarios', [SeguridadController::class, 'storeUser'])->name('seguridad.usuarios.store');
    Route::delete('/seguridad-acceso/usuarios/{user}', [SeguridadController::class, 'destroyUser'])
        ->whereNumber('user')
        ->name('seguridad.usuarios.destroy');

    Route::patch('/seguridad-acceso/usuarios/{user}/activar', [SeguridadController::class, 'activateUser'])
        ->whereNumber('user')
        ->name('seguridad.usuarios.activate');

    Route::post('/seguridad-acceso/roles', [SeguridadController::class, 'storeRole'])->name('seguridad.roles.store');
    Route::delete('/seguridad-acceso/roles/{role}', [SeguridadController::class, 'destroyRole'])
        ->whereNumber('role')
        ->name('seguridad.roles.destroy');

    Route::patch('/seguridad-acceso/roles/{role}/activar', [SeguridadController::class, 'activateRole'])
        ->whereNumber('role')
        ->name('seguridad.roles.activate');

    Route::post('/seguridad-acceso/permisos', [SeguridadController::class, 'storePermission'])
        ->name('seguridad.permisos.store');

    Route::delete('/seguridad-acceso/permisos/{permission}', [SeguridadController::class, 'destroyPermission'])
        ->whereNumber('permission')
        ->name('seguridad.permisos.destroy');

    Route::patch('/seguridad-acceso/permisos/{permission}/activar', [SeguridadController::class, 'activatePermission'])
        ->whereNumber('permission')
        ->name('seguridad.permisos.activate');
});
