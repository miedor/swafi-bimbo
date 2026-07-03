@extends('layouts.app')

@section('title', 'Reportes ad hoc | SWAFI')
@section('page_title', 'Reportes ad hoc')
@section('page_subtitle', 'Generación dinámica de reportes ejecutivos y operativos')
@section('breadcrumb', 'Reportes')

@section('page_styles')
<style>
    .rp-grid {
        display: grid;
        grid-template-columns: 0.9fr 1.1fr;
        gap: 18px;
        align-items: start;
    }

    .rp-filter {
        padding: 14px;
        border: 1px solid #e1eaf6;
        border-radius: 18px;
        background: #f8fbff;
    }

    .rp-filter label {
        display: block;
    }

    .rp-filter span {
        display: block;
        margin-bottom: 5px;
        color: #1d3558;
        font-size: 12px;
        font-weight: 900;
    }

    .rp-filter input,
    .rp-filter select {
        width: 100%;
        min-height: 38px;
        padding: 8px 10px;
        border: 1px solid #d5e1ef;
        border-radius: 11px;
        background: #ffffff;
        color: #16304d;
        font-size: 13px;
    }

    .rp-kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-top: 14px;
    }

    .rp-kpi {
        padding: 12px;
        border: 1px solid #e1eaf6;
        border-radius: 15px;
        background: #f8fbff;
    }

    .rp-kpi strong {
        display: block;
        color: #12345a;
        font-size: 18px;
        font-weight: 900;
    }

    .rp-kpi span {
        color: #64748b;
        font-size: 12px;
    }

    .rp-help {
        margin-top: 12px;
        padding: 10px 12px;
        border-radius: 13px;
        background: #eef6ff;
        color: #385b82;
        font-size: 12px;
        line-height: 1.4;
    }

    .rp-message {
        margin-bottom: 14px;
        padding: 11px 13px;
        border-radius: 13px;
        font-size: 13px;
        font-weight: 700;
    }

    .rp-message-success {
        background: #e8f7ea;
        color: #1f6b2a;
        border: 1px solid #b9e5bf;
    }

    .rp-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 5px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 900;
        white-space: nowrap;
    }

    .rp-status-ok {
        background: #e8f7ea;
        color: #1f6b2a;
    }

    .rp-status-warn {
        background: #fff4d6;
        color: #8a5a00;
    }

    .rp-status-danger {
        background: #fdeaea;
        color: #8a1f1f;
    }

    .rp-table-scroll {
        width: 100%;
        overflow-x: auto;
    }

    @media (max-width: 1100px) {
        .rp-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 760px) {
        .query-grid-four,
        .rp-kpi-grid {
            grid-template-columns: 1fr !important;
        }
    }
</style>
@endsection

@section('content')

@if (session('success'))
    <div class="rp-message rp-message-success">
        {{ session('success') }}
    </div>
@endif

<section class="rp-grid">
    <div class="card">
        <div class="section-title">
            <h2>Generador de reportes</h2>
            <span class="pill ok">M03 funcional</span>
        </div>

        <form method="GET" action="{{ route('reportes') }}" class="rp-filter">
            <div class="query-grid query-grid-four">
                <label>
                    <span>Tipo de reporte</span>
                    <select name="tipo_reporte">
                        @foreach ($tiposReporte as $value => $label)
                            @php
                                $tipoSeleccionado = (string) ($tipoReporte ?? 'expedientes_documentales');
                            @endphp
                            <option value="{{ $value }}" {{ $tipoSeleccionado === (string) $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Número de activo</span>
                    <input name="numero_activo" value="{{ $filtros['numero_activo'] ?? '' }}" placeholder="Ej. BIM-537028">
                </label>

                <label>
                    <span>Fecha desde</span>
                    <input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
                </label>

                <label>
                    <span>Fecha hasta</span>
                    <input type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                </label>
            </div>

            <div class="query-grid query-grid-four" style="margin-top:10px">
                <label>
                    <span>Planta</span>
                    <select name="planta_id">
                        <option value="">Todas</option>
                        @foreach ($catalogos['plantas'] as $planta)
                            @php
                                $plantaSeleccionada = (string) ($filtros['planta_id'] ?? '');
                            @endphp
                            <option value="{{ $planta->id }}" {{ $plantaSeleccionada === (string) $planta->id ? 'selected' : '' }}>
                                {{ $planta->nombre }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Proveedor</span>
                    <select name="proveedor_id">
                        <option value="">Todos</option>
                        @foreach ($catalogos['proveedores'] as $proveedor)
                            @php
                                $proveedorSeleccionado = (string) ($filtros['proveedor_id'] ?? '');
                            @endphp
                            <option value="{{ $proveedor->id }}" {{ $proveedorSeleccionado === (string) $proveedor->id ? 'selected' : '' }}>
                                {{ $proveedor->nombre }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Centro de costo</span>
                    <select name="centro_costo_id">
                        <option value="">Todos</option>
                        @foreach ($catalogos['centrosCosto'] as $centro)
                            @php
                                $centroSeleccionado = (string) ($filtros['centro_costo_id'] ?? '');
                            @endphp
                            <option value="{{ $centro->id }}" {{ $centroSeleccionado === (string) $centro->id ? 'selected' : '' }}>
                                {{ $centro->clave }} - {{ $centro->descripcion }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Tipo de activo</span>
                    <select name="tipo_activo_id">
                        <option value="">Todos</option>
                        @foreach ($catalogos['tiposActivo'] as $tipo)
                            @php
                                $tipoSeleccionadoFiltro = (string) ($filtros['tipo_activo_id'] ?? '');
                            @endphp
                            <option value="{{ $tipo->id }}" {{ $tipoSeleccionadoFiltro === (string) $tipo->id ? 'selected' : '' }}>
                                {{ $tipo->descripcion }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="query-grid query-grid-four" style="margin-top:10px">
                <label>
                    <span>Estatus documental</span>
                    @php
                        $estatusDocumental = $filtros['estatus_documental'] ?? '';
                    @endphp
                    <select name="estatus_documental">
                        <option value="">Todos</option>
                        <option value="completo" {{ $estatusDocumental === 'completo' ? 'selected' : '' }}>Completo</option>
                        <option value="incompleto" {{ $estatusDocumental === 'incompleto' ? 'selected' : '' }}>Incompleto</option>
                        <option value="observado" {{ $estatusDocumental === 'observado' ? 'selected' : '' }}>Observado</option>
                    </select>
                </label>

                <label>
                    <span>Estatus contable</span>
                    @php
                        $estatusContable = $filtros['estatus_contable'] ?? '';
                    @endphp
                    <select name="estatus_contable">
                        <option value="">Todos</option>
                        <option value="vigente" {{ $estatusContable === 'vigente' ? 'selected' : '' }}>Vigente</option>
                        <option value="en_revision" {{ $estatusContable === 'en_revision' ? 'selected' : '' }}>En revisión</option>
                        <option value="baja" {{ $estatusContable === 'baja' ? 'selected' : '' }}>Baja</option>
                    </select>
                </label>

                <label>
                    <span>Estatus inventario</span>
                    @php
                        $estatusLocalizacion = $filtros['estatus_localizacion'] ?? '';
                    @endphp
                    <select name="estatus_localizacion">
                        <option value="">Todos</option>
                        <option value="localizado" {{ $estatusLocalizacion === 'localizado' ? 'selected' : '' }}>Localizado</option>
                        <option value="no_encontrado" {{ $estatusLocalizacion === 'no_encontrado' ? 'selected' : '' }}>No encontrado</option>
                        <option value="diferencia" {{ $estatusLocalizacion === 'diferencia' ? 'selected' : '' }}>Diferencia</option>
                        <option value="pendiente" {{ $estatusLocalizacion === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                        <option value="sin_inventario" {{ $estatusLocalizacion === 'sin_inventario' ? 'selected' : '' }}>Sin inventario</option>
                    </select>
                </label>

                <label>
                    <span>Registros por página</span>
                    <select name="per_page">
                        @foreach ([10, 25, 50] as $size)
                            @php
                                $perPageSeleccionado = (string) ($filtros['per_page'] ?? 10);
                            @endphp
                            <option value="{{ $size }}" {{ $perPageSeleccionado === (string) $size ? 'selected' : '' }}>
                                {{ $size }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="query-grid query-grid-four" style="margin-top:10px">
                <label>
                    <span>Área</span>
                    <select name="area_id">
                        <option value="">Todas</option>
                        @foreach ($catalogos['areas'] as $area)
                            @php
                                $areaSeleccionada = (string) ($filtros['area_id'] ?? '');
                            @endphp
                            <option value="{{ $area->id }}" {{ $areaSeleccionada === (string) $area->id ? 'selected' : '' }}>
                                {{ $area->nombre }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Responsable</span>
                    <select name="responsable_id">
                        <option value="">Todos</option>
                        @foreach ($catalogos['responsables'] as $responsable)
                            @php
                                $responsableSeleccionado = (string) ($filtros['responsable_id'] ?? '');
                            @endphp
                            <option value="{{ $responsable->id }}" {{ $responsableSeleccionado === (string) $responsable->id ? 'selected' : '' }}>
                                {{ $responsable->nombre }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Monto / valor desde</span>
                    <input type="number" step="0.01" name="monto_desde" value="{{ $filtros['monto_desde'] ?? '' }}">
                </label>

                <label>
                    <span>Monto / valor hasta</span>
                    <input type="number" step="0.01" name="monto_hasta" value="{{ $filtros['monto_hasta'] ?? '' }}">
                </label>
            </div>

            <div class="action-group" style="margin-top:12px">
                <button class="tab" type="submit">Consultar</button>
                <button class="tab" type="submit" name="export" value="csv">Exportar CSV</button>
                <a class="tab" href="{{ route('reportes') }}">Limpiar filtros</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="section-title">
            <h2>Resumen del reporte</h2>
            <span class="pill ok">{{ $kpis['exportable'] }}</span>
        </div>

        <div class="rp-kpi-grid">
            <div class="rp-kpi">
                <strong>{{ number_format((int) ($kpis['total'] ?? 0)) }}</strong>
                <span>Registros filtrados</span>
            </div>

            <div class="rp-kpi">
                <strong>{{ $kpis['sumatoria'] !== null ? '$ ' . number_format((float) $kpis['sumatoria'], 2) : 'N/A' }}</strong>
                <span>{{ $kpis['sumatoria_label'] }}</span>
            </div>

            <div class="rp-kpi">
                <strong>M03</strong>
                <span>Consultas y reportes</span>
            </div>

            <div class="rp-kpi">
                <strong>CSV</strong>
                <span>Exportación activa</span>
            </div>
        </div>

        <div class="rp-help">
            Reporte actual: <strong>{{ $tiposReporte[$tipoReporte] ?? 'Reporte' }}</strong>.
            Esta pantalla consolida expedientes, documentación PDF/XML, valores fiscales, ubicación física e inventario
            para facilitar análisis, seguimiento y auditoría dentro de SWAFI.
        </div>

        <div class="quick-links" style="margin-top:14px">
            <a href="{{ route('reportes', ['tipo_reporte' => 'expedientes_documentales']) }}">Expedientes</a>
            <a href="{{ route('reportes', ['tipo_reporte' => 'expedientes_incompletos']) }}">Incompletos</a>
            <a href="{{ route('reportes', ['tipo_reporte' => 'valores_fiscales']) }}">Valores</a>
            <a href="{{ route('reportes', ['tipo_reporte' => 'ubicacion_inventario']) }}">Ubicación</a>
        </div>
    </div>
</section>

<section class="card table-card" style="margin-top:20px">
    <div class="section-title">
        <h2>Resultados del reporte</h2>
        <span class="pill ok">Paginación real</span>
    </div>

    <div class="rp-table-scroll">
        <table>
            <thead>
                <tr>
                    @foreach ($columnas as $label)
                        <th>{{ $label }}</th>
                    @endforeach
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($resultados as $row)
                    <tr>
                        @foreach ($columnas as $key => $label)
                            @php
                                $value = data_get($row, $key);
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
                                    'estatus_localizacion',
                                ];
                            @endphp

                            <td>
                                @if (in_array($key, $moneyKeys, true) && $value !== null)
                                    $ {{ number_format((float) $value, 2) }}
                                @elseif (in_array($key, $statusKeys, true))
                                    @php
                                        $statusValue = (string) ($value ?: 'sin_informacion');

                                        if (in_array($statusValue, ['completo', 'vigente', 'localizado'], true)) {
                                            $statusClass = 'rp-status-ok';
                                        } elseif (in_array($statusValue, ['observado', 'en_revision', 'diferencia', 'pendiente'], true)) {
                                            $statusClass = 'rp-status-warn';
                                        } else {
                                            $statusClass = 'rp-status-danger';
                                        }
                                    @endphp

                                    <span class="rp-status {{ $statusClass }}">
                                        {{ ucfirst(str_replace('_', ' ', $statusValue)) }}
                                    </span>
                                @else
                                    {{ $value !== null && $value !== '' ? $value : '—' }}
                                @endif
                            </td>
                        @endforeach

                        <td>
                            <div class="table-actions">
                                @if (isset($row->expediente_id))
                                    <a href="{{ route('expediente', $row->expediente_id) }}">Consultar</a>
                                @endif

                                @if (isset($row->numero_activo))
                                    <a href="{{ route('busqueda', ['numero_activo' => $row->numero_activo]) }}">Buscar</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columnas) + 1 }}">
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
            <span>M03 reportes ad hoc funcional</span>
        </div>
    </div>
</section>

@endsection
