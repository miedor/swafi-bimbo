@extends('layouts.app')

@section('title', 'Centro de reportes | SWAFI')
@section('page_title', 'Centro de reportes')
@section('page_subtitle', 'Reportes ad hoc, plantillas personales y exportación controlada')
@section('breadcrumb', 'Reportes')

@section('page_styles')
<style>
    .rp-shell {
        display: grid;
        gap: 14px;
    }

    .rp-top {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 330px;
        gap: 14px;
        align-items: start;
    }

    .rp-card {
        padding: 15px;
        border: 1px solid #dbe7f6;
        border-radius: 20px;
        background: #ffffff;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
    }

    .rp-title-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        margin-bottom: 12px;
    }

    .rp-title-row h2,
    .rp-title-row h3 {
        margin: 0;
        color: #15355d;
        font-weight: 950;
    }

    .rp-title-row h2 {
        font-size: 18px;
    }

    .rp-title-row h3 {
        font-size: 15px;
    }

    .rp-form-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(150px, 1fr));
        gap: 10px;
    }

    .rp-field span {
        display: block;
        margin-bottom: 5px;
        color: #1d3558;
        font-size: 11.5px;
        font-weight: 900;
    }

    .rp-field input,
    .rp-field select {
        width: 100%;
        min-height: 38px;
        padding: 8px 10px;
        border: 1px solid #d5e1ef;
        border-radius: 11px;
        background: #ffffff;
        color: #16304d;
        font-size: 13px;
    }

    .rp-details {
        margin-top: 10px;
        border: 1px solid #e0e9f5;
        border-radius: 15px;
        background: #f8fbff;
    }

    .rp-details summary {
        padding: 10px 12px;
        color: #174f9a;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
    }

    .rp-details-body {
        padding: 0 12px 12px;
    }

    .rp-column-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(150px, 1fr));
        gap: 7px;
    }

    .rp-check {
        display: flex;
        gap: 8px;
        align-items: flex-start;
        min-height: 36px;
        padding: 8px 9px;
        border: 1px solid #dfe9f7;
        border-radius: 11px;
        background: #ffffff;
        color: #294867;
        font-size: 12px;
        font-weight: 800;
    }

    .rp-check input {
        width: auto;
        min-height: auto;
        margin-top: 2px;
    }

    .rp-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
    }

    .rp-actions .tab {
        min-height: 38px;
    }

    .rp-kpis {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
    }

    .rp-kpi {
        min-height: 76px;
        padding: 11px;
        border: 1px solid #dfe9f7;
        border-radius: 15px;
        background: #f8fbff;
    }

    .rp-kpi strong {
        display: block;
        color: #12345c;
        font-size: 20px;
        font-weight: 950;
        line-height: 1.05;
    }

    .rp-kpi span {
        display: block;
        margin-top: 5px;
        color: #64748b;
        font-size: 11px;
        font-weight: 800;
        line-height: 1.2;
    }

    .rp-saved-list {
        display: grid;
        gap: 8px;
        max-height: 245px;
        overflow-y: auto;
        padding-right: 3px;
    }

    .rp-saved-item {
        padding: 10px;
        border: 1px solid #dfe9f7;
        border-radius: 14px;
        background: #ffffff;
    }

    .rp-saved-item strong {
        display: block;
        color: #14355f;
        font-size: 12.5px;
        font-weight: 950;
    }

    .rp-saved-item small {
        display: block;
        margin-top: 3px;
        color: #64748b;
        font-size: 11px;
    }

    .rp-saved-actions {
        display: flex;
        gap: 8px;
        margin-top: 8px;
    }

    .rp-link-button {
        border: 0;
        background: none;
        color: #174f9a;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
        padding: 0;
        text-decoration: none;
    }

    .rp-link-button.danger {
        color: #b42318;
    }

    .rp-table-wrap {
        width: 100%;
        max-height: 430px;
        overflow: auto;
        border: 1px solid #e2ebf6;
        border-radius: 16px;
    }

    .rp-table-wrap table {
        min-width: 920px;
    }

    .rp-table-wrap th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f6faff;
    }

    .rp-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 5px 8px;
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 900;
        white-space: nowrap;
    }

    .rp-status.ok {
        background: #e8f7ea;
        color: #1f6b2a;
    }

    .rp-status.warn {
        background: #fff4d6;
        color: #8a5a00;
    }

    .rp-status.danger {
        background: #fdeaea;
        color: #8a1f1f;
    }

    .rp-message {
        padding: 11px 13px;
        border-radius: 13px;
        font-size: 13px;
        font-weight: 800;
    }

    .rp-message.success {
        border: 1px solid #b9e5bf;
        background: #e8f7ea;
        color: #1f6b2a;
    }

    .rp-message.error {
        border: 1px solid #fecaca;
        background: #fff0ee;
        color: #b42318;
    }

    .rp-note {
        margin-top: 10px;
        padding: 9px 10px;
        border-radius: 12px;
        background: #eef6ff;
        color: #385b82;
        font-size: 11.5px;
        font-weight: 750;
        line-height: 1.35;
    }

    .rp-hidden-group {
        display: none !important;
    }

    @media (max-width: 1250px) {
        .rp-top {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 900px) {
        .rp-form-grid,
        .rp-column-grid {
            grid-template-columns: repeat(2, minmax(150px, 1fr));
        }
    }

    @media (max-width: 640px) {
        .rp-form-grid,
        .rp-column-grid,
        .rp-kpis {
            grid-template-columns: 1fr;
        }

        .rp-actions .tab {
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endsection

@section('content')

@php
    $filtros = $filtros ?? [];
    $moneyKeys = [
        'monto_factura',
        'valor_fiscal',
        'depreciacion_acumulada',
        'valor_en_libros',
        'valor_financiero',
    ];

    $statusKeys = [
        'estatus_documental',
        'estatus_contable',
        'estatus_operativo',
        'estatus_localizacion',
        'ultimo_estatus_localizacion',
    ];

    $selectedColumnKeys = array_keys($columnasSeleccionadas);
@endphp

<div class="rp-shell" data-swafi-query-workspace data-swafi-query-key="reportes">
    @if (session('success'))
        <div class="rp-message success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="rp-message error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="rp-top" data-swafi-query-panel>
        <section class="rp-card">
            <div class="rp-title-row">
                <h2>Generador ad hoc</h2>
                <span class="pill ok">Permisos por reporte</span>
            </div>

            <form method="GET" action="{{ route('reportes') }}" id="reportForm" data-swafi-query-form>
                <div class="rp-form-grid">
                    <label class="rp-field">
                        <span>Tipo de reporte</span>
                        <select name="tipo_reporte" id="tipoReporte">
                            @foreach ($tiposReporte as $value => $label)
                                <option value="{{ $value }}" {{ (string) $tipoReporte === (string) $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="rp-field" data-report-group="general">
                        <span>Número de activo</span>
                        <input name="numero_activo" value="{{ $filtros['numero_activo'] ?? '' }}" placeholder="Ej. BIM-537028">
                    </label>

                    <label class="rp-field" data-report-group="general">
                        <span>{{ $tipoReporte === 'activos_no_verificados' ? 'Periodo de verificación desde' : 'Fecha desde' }}</span>
                        <input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
                    </label>

                    <label class="rp-field" data-report-group="general">
                        <span>{{ $tipoReporte === 'activos_no_verificados' ? 'Periodo de verificación hasta' : 'Fecha hasta' }}</span>
                        <input type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                    </label>
                </div>

                <details class="rp-details" open>
                    <summary>Filtros del reporte</summary>
                    <div class="rp-details-body">
                        <div class="rp-form-grid">
                            <label class="rp-field" data-report-group="asset">
                                <span>Planta</span>
                                <select name="planta_id">
                                    <option value="">Todas</option>
                                    @foreach ($catalogos['plantas'] as $planta)
                                        <option value="{{ $planta->id }}" {{ (string) ($filtros['planta_id'] ?? '') === (string) $planta->id ? 'selected' : '' }}>
                                            {{ $planta->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="rp-field" data-report-group="documental valores">
                                <span>Proveedor</span>
                                <select name="proveedor_id">
                                    <option value="">Todos</option>
                                    @foreach ($catalogos['proveedores'] as $proveedor)
                                        <option value="{{ $proveedor->id }}" {{ (string) ($filtros['proveedor_id'] ?? '') === (string) $proveedor->id ? 'selected' : '' }}>
                                            {{ $proveedor->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="rp-field" data-report-group="documental valores">
                                <span>Centro de costo</span>
                                <select name="centro_costo_id">
                                    <option value="">Todos</option>
                                    @foreach ($catalogos['centrosCosto'] as $centro)
                                        <option value="{{ $centro->id }}" {{ (string) ($filtros['centro_costo_id'] ?? '') === (string) $centro->id ? 'selected' : '' }}>
                                            {{ $centro->clave }} - {{ $centro->descripcion }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="rp-field" data-report-group="documental valores">
                                <span>Tipo de activo</span>
                                <select name="tipo_activo_id">
                                    <option value="">Todos</option>
                                    @foreach ($catalogos['tiposActivo'] as $tipo)
                                        <option value="{{ $tipo->id }}" {{ (string) ($filtros['tipo_activo_id'] ?? '') === (string) $tipo->id ? 'selected' : '' }}>
                                            {{ $tipo->descripcion }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="rp-field" data-report-group="inventario">
                                <span>Área</span>
                                <select name="area_id">
                                    <option value="">Todas</option>
                                    @foreach ($catalogos['areas'] as $area)
                                        <option value="{{ $area->id }}" {{ (string) ($filtros['area_id'] ?? '') === (string) $area->id ? 'selected' : '' }}>
                                            {{ $area->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="rp-field" data-report-group="ubicacion">
                                <span>Responsable</span>
                                <select name="responsable_id">
                                    <option value="">Todos</option>
                                    @foreach ($catalogos['responsables'] as $responsable)
                                        <option value="{{ $responsable->id }}" {{ (string) ($filtros['responsable_id'] ?? '') === (string) $responsable->id ? 'selected' : '' }}>
                                            {{ $responsable->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="rp-field" data-report-group="bitacora">
                                <span>Usuario de bitácora</span>
                                <select name="usuario_id">
                                    <option value="">Todos</option>
                                    @foreach ($catalogos['usuarios'] as $usuario)
                                        <option value="{{ $usuario->id }}" {{ (string) ($filtros['usuario_id'] ?? '') === (string) $usuario->id ? 'selected' : '' }}>
                                            {{ $usuario->name }} - {{ $usuario->email }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="rp-field" data-report-group="bitacora">
                                <span>Módulo de bitácora</span>
                                <input name="modulo" value="{{ $filtros['modulo'] ?? '' }}" placeholder="Ej. M03 Consultas">
                            </label>

                            <label class="rp-field" data-report-group="documental">
                                <span>Estatus documental</span>
                                <select name="estatus_documental">
                                    <option value="">Todos</option>
                                    <option value="completo" {{ ($filtros['estatus_documental'] ?? '') === 'completo' ? 'selected' : '' }}>Completo</option>
                                    <option value="incompleto" {{ ($filtros['estatus_documental'] ?? '') === 'incompleto' ? 'selected' : '' }}>Incompleto</option>
                                    <option value="observado" {{ ($filtros['estatus_documental'] ?? '') === 'observado' ? 'selected' : '' }}>Observado</option>
                                </select>
                            </label>

                            <label class="rp-field" data-report-group="valores">
                                <span>Estatus contable</span>
                                <select name="estatus_contable">
                                    <option value="">Todos</option>
                                    <option value="vigente" {{ ($filtros['estatus_contable'] ?? '') === 'vigente' ? 'selected' : '' }}>Vigente</option>
                                    <option value="en_revision" {{ ($filtros['estatus_contable'] ?? '') === 'en_revision' ? 'selected' : '' }}>En revisión</option>
                                    <option value="baja" {{ ($filtros['estatus_contable'] ?? '') === 'baja' ? 'selected' : '' }}>Baja</option>
                                </select>
                            </label>

                            <label class="rp-field" data-report-group="asset">
                                <span>Estatus operativo</span>
                                <select name="estatus_operativo">
                                    <option value="">Todos</option>
                                    <option value="en_operacion" {{ ($filtros['estatus_operativo'] ?? '') === 'en_operacion' ? 'selected' : '' }}>En operación</option>
                                    <option value="traslado" {{ ($filtros['estatus_operativo'] ?? '') === 'traslado' ? 'selected' : '' }}>Traslado</option>
                                    <option value="baja" {{ ($filtros['estatus_operativo'] ?? '') === 'baja' ? 'selected' : '' }}>Baja</option>
                                </select>
                            </label>

                            <label class="rp-field" data-report-group="inventario">
                                <span>Estatus de inventario</span>
                                <select name="estatus_localizacion">
                                    <option value="">Todos</option>
                                    <option value="localizado" {{ ($filtros['estatus_localizacion'] ?? '') === 'localizado' ? 'selected' : '' }}>Localizado</option>
                                    <option value="no_encontrado" {{ ($filtros['estatus_localizacion'] ?? '') === 'no_encontrado' ? 'selected' : '' }}>No encontrado</option>
                                    <option value="diferencia" {{ ($filtros['estatus_localizacion'] ?? '') === 'diferencia' ? 'selected' : '' }}>Diferencia</option>
                                    <option value="pendiente" {{ ($filtros['estatus_localizacion'] ?? '') === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                                    <option value="sin_inventario" {{ ($filtros['estatus_localizacion'] ?? '') === 'sin_inventario' ? 'selected' : '' }}>Sin inventario</option>
                                </select>
                            </label>

                            <label class="rp-field" data-report-group="monetario">
                                <span>Monto / valor desde</span>
                                <input type="number" step="0.01" min="0" name="monto_desde" value="{{ $filtros['monto_desde'] ?? '' }}">
                            </label>

                            <label class="rp-field" data-report-group="monetario">
                                <span>Monto / valor hasta</span>
                                <input type="number" step="0.01" min="0" name="monto_hasta" value="{{ $filtros['monto_hasta'] ?? '' }}">
                            </label>

                            <label class="rp-field">
                                <span>Ordenar por</span>
                                <select name="ordenar_por">
                                    <option value="">Orden predeterminado</option>
                                    <option value="numero_activo" {{ ($filtros['ordenar_por'] ?? '') === 'numero_activo' ? 'selected' : '' }}>Número de activo</option>
                                    <option value="fecha" {{ ($filtros['ordenar_por'] ?? '') === 'fecha' ? 'selected' : '' }}>Fecha</option>
                                    <option value="monto" {{ ($filtros['ordenar_por'] ?? '') === 'monto' ? 'selected' : '' }}>Monto / valor</option>
                                    <option value="planta" {{ ($filtros['ordenar_por'] ?? '') === 'planta' ? 'selected' : '' }}>Planta</option>
                                    <option value="estatus" {{ ($filtros['ordenar_por'] ?? '') === 'estatus' ? 'selected' : '' }}>Estatus</option>
                                    <option value="usuario" {{ ($filtros['ordenar_por'] ?? '') === 'usuario' ? 'selected' : '' }}>Usuario</option>
                                    <option value="modulo" {{ ($filtros['ordenar_por'] ?? '') === 'modulo' ? 'selected' : '' }}>Módulo</option>
                                    <option value="accion" {{ ($filtros['ordenar_por'] ?? '') === 'accion' ? 'selected' : '' }}>Acción</option>
                                </select>
                            </label>

                            <label class="rp-field">
                                <span>Dirección</span>
                                <select name="direccion">
                                    <option value="desc" {{ ($filtros['direccion'] ?? 'desc') === 'desc' ? 'selected' : '' }}>Descendente</option>
                                    <option value="asc" {{ ($filtros['direccion'] ?? '') === 'asc' ? 'selected' : '' }}>Ascendente</option>
                                </select>
                            </label>

                            <label class="rp-field">
                                <span>Registros por página</span>
                                <select name="per_page">
                                    @foreach ([10, 25, 50, 100] as $size)
                                        <option value="{{ $size }}" {{ (string) ($filtros['per_page'] ?? 10) === (string) $size ? 'selected' : '' }}>
                                            {{ $size }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>
                </details>

                <details class="rp-details">
                    <summary>Columnas del reporte (selecciona hasta 16)</summary>
                    <div class="rp-details-body">
                        <div class="rp-column-grid">
                            @foreach ($columnasDisponibles as $key => $label)
                                <label class="rp-check">
                                    <input
                                        type="checkbox"
                                        name="columnas[]"
                                        value="{{ $key }}"
                                        {{ in_array($key, $selectedColumnKeys, true) ? 'checked' : '' }}
                                    >
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </details>

                <input type="hidden" name="orientacion" value="horizontal">

                <div class="rp-actions">
                    <button class="tab" type="submit">Generar reporte</button>
                    <button class="tab" type="submit" name="export" value="csv">Exportar CSV</button>

                    @if ($canExportExcel)
                        <button class="tab" type="submit" name="export" value="xlsx">Exportar Excel</button>
                    @endif

                    @if ($canExportPdf)
                        <button class="tab" type="submit" name="export" value="pdf">Exportar PDF</button>
                    @endif

                    <a class="tab" href="{{ route('reportes') }}">Limpiar</a>
                </div>
            </form>

            <div class="rp-note">
                Las exportaciones respetan filtros y columnas. El límite por archivo es de
                <strong>{{ number_format($exportLimit) }} registros</strong> para proteger el rendimiento de SWAFI.
            </div>
        </section>

        <aside class="rp-card">
            <div class="rp-title-row">
                <h3>Resumen ejecutivo</h3>
                <span class="pill ok">{{ $kpis['exportable'] }}</span>
            </div>

            <div class="rp-kpis">
                <div class="rp-kpi">
                    <strong>{{ number_format((int) ($kpis['total'] ?? 0)) }}</strong>
                    <span>Registros filtrados</span>
                </div>

                <div class="rp-kpi">
                    <strong>{{ $kpis['sumatoria'] !== null ? '$ ' . number_format((float) $kpis['sumatoria'], 2) : 'N/A' }}</strong>
                    <span>{{ $kpis['sumatoria_label'] }}</span>
                </div>

                <div class="rp-kpi">
                    <strong>{{ count($columnasSeleccionadas) }}</strong>
                    <span>Columnas seleccionadas</span>
                </div>

                <div class="rp-kpi">
                    <strong>{{ $reportesGuardados->count() }}</strong>
                    <span>Reportes guardados</span>
                </div>
            </div>

            @if ($canSaveReports)
                <form method="POST" action="{{ route('reportes-guardados.store') }}" style="margin-top:12px">
                    @csrf

                    @foreach ($filtros as $key => $value)
                        @if (!in_array($key, ['page', 'export', 'columnas', 'nombre_reporte_guardado'], true) && !is_array($value) && $value !== null && $value !== '')
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach

                    <input type="hidden" name="tipo_reporte" value="{{ $tipoReporte }}">
                    <input type="hidden" name="orientacion" value="horizontal">

                    @foreach ($selectedColumnKeys as $columnKey)
                        <input type="hidden" name="columnas[]" value="{{ $columnKey }}">
                    @endforeach

                    <label class="rp-field">
                        <span>Guardar parámetros actuales</span>
                        <input name="nombre_reporte_guardado" maxlength="120" placeholder="Ej. Discrepancias Planta Norte" required>
                    </label>

                    <div class="rp-actions" style="margin-top:8px">
                        <button class="tab" type="submit">Guardar plantilla</button>
                    </div>
                </form>
            @endif

            <div class="rp-title-row" style="margin-top:14px;margin-bottom:8px">
                <h3>Mis reportes guardados</h3>
            </div>

            <div class="rp-saved-list">
                @forelse ($reportesGuardados as $savedReport)
                    <div class="rp-saved-item">
                        <strong>{{ $savedReport->nombre }}</strong>
                        <small>{{ $tiposReporte[$savedReport->tipo_reporte] ?? str_replace('_', ' ', $savedReport->tipo_reporte) }}</small>
                        <div class="rp-saved-actions">
                            <a class="rp-link-button" href="{{ route('reportes-guardados.apply', $savedReport->id) }}">Aplicar</a>

                            <form method="POST" action="{{ route('reportes-guardados.destroy', $savedReport->id) }}" onsubmit="return confirm('¿Deseas dar de baja lógicamente este reporte guardado? La configuración se conservará para trazabilidad.');">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="motivo_baja" value="Baja lógica solicitada por la persona propietaria del reporte.">
                                <button class="rp-link-button danger" type="submit">Dar de baja</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rp-note" style="margin-top:0">Aún no has guardado parámetros de reporte.</div>
                @endforelse
            </div>
        </aside>
    </div>

    <section class="rp-card" data-swafi-query-results id="swafi-reportes-resultados">
        <div class="rp-title-row">
            <h2>{{ $kpis['tipo'] }}</h2>
            <span class="pill ok">Paginación y auditoría</span>
        </div>

        <div class="rp-table-wrap">
            <table>
                <thead>
                    <tr>
                        @foreach ($columnasSeleccionadas as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($resultados as $row)
                        <tr>
                            @foreach ($columnasSeleccionadas as $key => $label)
                                @php
                                    $value = data_get($row, $key);
                                    $statusClass = 'danger';

                                    if (in_array((string) $value, ['completo', 'vigente', 'localizado', 'en_operacion'], true)) {
                                        $statusClass = 'ok';
                                    } elseif (in_array((string) $value, ['observado', 'en_revision', 'diferencia', 'pendiente', 'traslado'], true)) {
                                        $statusClass = 'warn';
                                    }
                                @endphp

                                <td>
                                    @if (in_array($key, $moneyKeys, true) && $value !== null && $value !== '')
                                        $ {{ number_format((float) $value, 2) }}
                                    @elseif (in_array($key, $statusKeys, true) && $value !== null && $value !== '')
                                        <span class="rp-status {{ $statusClass }}">
                                            {{ ucfirst(str_replace('_', ' ', (string) $value)) }}
                                        </span>
                                    @else
                                        {{ $value !== null && $value !== '' ? $value : '—' }}
                                    @endif
                                </td>
                            @endforeach

                            <td>
                                <div class="table-actions">
                                    @if (isset($row->expediente_id) && $row->expediente_id)
                                        <a href="{{ route('expediente', $row->expediente_id) }}">Consultar</a>
                                    @endif

                                    @if (isset($row->numero_activo) && $row->numero_activo)
                                        <a href="{{ route('busqueda', ['numero_activo' => $row->numero_activo]) }}">Buscar</a>
                                        <a href="{{ route('activos.etiqueta', $row->numero_activo) }}" target="_blank" rel="noopener">Etiqueta QR</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columnasSeleccionadas) + 1 }}">
                                No existen resultados con los criterios seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <div class="table-summary">
                Mostrando {{ $resultados->firstItem() ?? 0 }}–{{ $resultados->lastItem() ?? 0 }}
                de {{ $resultados->total() }} resultados
            </div>

            <div class="table-pagination">
                @if ($resultados->onFirstPage())
                    <span class="page-link disabled">Anterior</span>
                @else
                    <a class="page-link" href="{{ $resultados->previousPageUrl() }}">Anterior</a>
                @endif

                <span class="page-link active">{{ $resultados->currentPage() }}</span>

                @if ($resultados->hasMorePages())
                    <a class="page-link" href="{{ $resultados->nextPageUrl() }}">Siguiente</a>
                @else
                    <span class="page-link disabled">Siguiente</span>
                @endif
            </div>

            <div class="table-page-size">
                <span>Centro de reportes SWAFI</span>
            </div>
        </div>
    </section>
</div>

@endsection

@section('page_scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const reportType = document.getElementById('tipoReporte');
        const groups = document.querySelectorAll('[data-report-group]');

        const typeGroups = {
            expedientes_documentales: ['general', 'asset', 'documental', 'monetario'],
            expedientes_incompletos: ['general', 'asset', 'documental', 'monetario'],
            activos_sin_documentacion: ['general', 'asset', 'documental'],
            valores_fiscales: ['general', 'asset', 'valores', 'monetario'],
            ubicacion_inventario: ['general', 'asset', 'inventario', 'ubicacion'],
            activos_no_verificados: ['general', 'asset', 'inventario', 'ubicacion'],
            discrepancias_inventario: ['general', 'asset', 'inventario'],
            actividad_bitacora: ['general', 'bitacora']
        };

        function refreshGroups() {
            const selectedType = reportType ? reportType.value : 'expedientes_documentales';
            const allowed = typeGroups[selectedType] || ['general'];

            groups.forEach(function (element) {
                const elementGroups = (element.getAttribute('data-report-group') || '').split(/\s+/);
                const visible = elementGroups.some(function (group) {
                    return allowed.includes(group);
                });

                element.classList.toggle('rp-hidden-group', !visible);
            });
        }

        if (reportType) {
            reportType.addEventListener('change', refreshGroups);
        }

        refreshGroups();
    });
</script>
@endsection
