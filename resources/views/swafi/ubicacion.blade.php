@extends('layouts.app')

@section('title', 'Ubicación física e inventario | SWAFI')
@section('page_title', 'Ubicación física e inventario')
@section('page_subtitle', 'Control de localización de activos y seguimiento de toma de inventario')
@section('breadcrumb', 'Ubicación física e inventario')

@section('page_styles')
<style>
    .ui-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
        align-items: start;
    }

    .ui-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .ui-field-wide {
        grid-column: 1 / -1;
    }

    .ui-form-grid label,
    .ui-filter label {
        display: block;
    }

    .ui-form-grid span,
    .ui-filter span {
        display: block;
        margin-bottom: 5px;
        color: #1d3558;
        font-size: 12px;
        font-weight: 900;
    }

    .ui-form-grid input,
    .ui-form-grid select,
    .ui-form-grid textarea,
    .ui-filter input,
    .ui-filter select {
        width: 100%;
        min-height: 38px;
        padding: 8px 10px;
        border: 1px solid #d5e1ef;
        border-radius: 11px;
        background: #ffffff;
        color: #16304d;
        font-size: 13px;
    }

    .ui-form-grid textarea {
        min-height: 76px;
        resize: vertical;
    }

    .ui-message {
        margin-bottom: 14px;
        padding: 11px 13px;
        border-radius: 13px;
        font-size: 13px;
        font-weight: 700;
    }

    .ui-message-success {
        background: #e8f7ea;
        color: #1f6b2a;
        border: 1px solid #b9e5bf;
    }

    .ui-message-error {
        background: #fdeaea;
        color: #8a1f1f;
        border: 1px solid #f2baba;
    }

    .ui-message ul {
        margin: 6px 0 0 18px;
    }

    .ui-filter {
        margin-top: 20px;
        padding: 14px;
        border: 1px solid #e1eaf6;
        border-radius: 18px;
        background: #f8fbff;
    }

    .ui-checkbox {
        display: flex !important;
        align-items: center;
        gap: 8px;
        margin-top: 4px;
    }

    .ui-checkbox input {
        width: auto !important;
        min-height: auto !important;
    }

    .ui-checkbox span {
        margin: 0;
        font-weight: 800;
    }

    .ui-kpi-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-top: 12px;
    }

    .ui-kpi {
        padding: 12px;
        border: 1px solid #e1eaf6;
        border-radius: 15px;
        background: #f8fbff;
    }

    .ui-kpi strong {
        display: block;
        color: #12345a;
        font-size: 16px;
        font-weight: 900;
    }

    .ui-kpi span {
        color: #64748b;
        font-size: 12px;
    }

    @media (max-width: 1100px) {
        .ui-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 760px) {
        .ui-form-grid,
        .query-grid-four,
        .ui-kpi-grid {
            grid-template-columns: 1fr !important;
        }
    }
</style>
@endsection

@section('content')

@if (session('success'))
    <div class="ui-message ui-message-success">
        {{ session('success') }}
    </div>
@endif

@if ($errors->any())
    <div class="ui-message ui-message-error">
        <strong>Se encontraron errores:</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $activoSeleccionado = old('numero_activo', $filtros['numero_activo'] ?? '');
@endphp

<section class="ui-grid">
    <div class="card">
        <div class="section-title">
            <h2>Cambio de ubicación física</h2>
            <span class="pill ok">Movimiento trazable</span>
        </div>

        <form method="POST" action="{{ route('ubicacion.movimiento') }}">
            @csrf

            <div class="ui-form-grid">
                <label class="ui-field-wide">
                    <span>Activo fijo</span>
                    <select name="numero_activo" required>
                        <option value="">Seleccione...</option>
                        @foreach ($catalogos['activos'] as $activo)
                            <option value="{{ $activo->numero_activo }}" {{ $activoSeleccionado === $activo->numero_activo ? 'selected' : '' }}>
                                {{ $activo->numero_activo }} - {{ $activo->descripcion }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="ui-field-wide">
                    <span>Nueva ubicación física</span>
                    <select name="ubicacion_destino_id" required>
                        <option value="">Seleccione...</option>
                        @foreach ($catalogos['ubicaciones'] as $ubicacion)
                            @php
                                $ubicacionLabel = trim(
                                    ($ubicacion->codigo_interno ? $ubicacion->codigo_interno . ' - ' : '') .
                                    ($ubicacion->planta_nombre ?? '') .
                                    ($ubicacion->area_nombre ? ' / ' . $ubicacion->area_nombre : '') .
                                    ($ubicacion->descripcion ? ' / ' . $ubicacion->descripcion : '')
                                );
                            @endphp
                            <option value="{{ $ubicacion->id }}" {{ old('ubicacion_destino_id') == $ubicacion->id ? 'selected' : '' }}>
                                {{ $ubicacionLabel ?: 'Ubicación ' . $ubicacion->id }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Responsable</span>
                    <select name="responsable_id">
                        <option value="">Sin cambio</option>
                        @foreach ($catalogos['responsables'] as $responsable)
                            <option value="{{ $responsable->id }}" {{ old('responsable_id') == $responsable->id ? 'selected' : '' }}>
                                {{ $responsable->nombre }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Fecha del movimiento</span>
                    <input
                        type="datetime-local"
                        name="fecha_movimiento"
                        value="{{ old('fecha_movimiento', now()->format('Y-m-d\TH:i')) }}"
                        required
                    >
                </label>

                <label class="ui-field-wide">
                    <span>Motivo</span>
                    <input
                        name="motivo"
                        value="{{ old('motivo') }}"
                        placeholder="Ej. Reubicación por inventario, traslado operativo, ajuste de planta"
                    >
                </label>

                <label class="ui-field-wide">
                    <span>Evidencia / comentario</span>
                    <textarea name="evidencia" placeholder="Describe la razón del movimiento o evidencia de soporte">{{ old('evidencia') }}</textarea>
                </label>
            </div>

            <div class="action-group" style="margin-top:14px">
                <button class="tab" type="submit">Guardar movimiento</button>
                <a class="tab" href="{{ route('ubicacion') }}">Limpiar</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="section-title">
            <h2>Toma de inventario</h2>
            <span class="pill ok">Verificación física</span>
        </div>

        <form method="POST" action="{{ route('ubicacion.inventario') }}">
            @csrf

            <div class="ui-form-grid">
                <label class="ui-field-wide">
                    <span>Activo fijo</span>
                    <select name="numero_activo" required>
                        <option value="">Seleccione...</option>
                        @foreach ($catalogos['activos'] as $activo)
                            <option value="{{ $activo->numero_activo }}" {{ $activoSeleccionado === $activo->numero_activo ? 'selected' : '' }}>
                                {{ $activo->numero_activo }} - {{ $activo->descripcion }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Fecha de inventario</span>
                    <input
                        type="date"
                        name="fecha_inventario"
                        value="{{ old('fecha_inventario', now()->format('Y-m-d')) }}"
                        required
                    >
                </label>

                <label>
                    <span>Estatus localización</span>
                    @php
                        $estatusInventario = old('estatus_localizacion', 'localizado');
                    @endphp
                    <select name="estatus_localizacion" required>
                        <option value="localizado" {{ $estatusInventario === 'localizado' ? 'selected' : '' }}>Localizado</option>
                        <option value="no_encontrado" {{ $estatusInventario === 'no_encontrado' ? 'selected' : '' }}>No encontrado</option>
                        <option value="diferencia" {{ $estatusInventario === 'diferencia' ? 'selected' : '' }}>Diferencia de ubicación</option>
                        <option value="pendiente" {{ $estatusInventario === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                    </select>
                </label>

                <label class="ui-field-wide">
                    <span>Ubicación verificada</span>
                    <select name="ubicacion_verificada_id">
                        <option value="">Sin ubicación verificada</option>
                        @foreach ($catalogos['ubicaciones'] as $ubicacion)
                            @php
                                $ubicacionLabel = trim(
                                    ($ubicacion->codigo_interno ? $ubicacion->codigo_interno . ' - ' : '') .
                                    ($ubicacion->planta_nombre ?? '') .
                                    ($ubicacion->area_nombre ? ' / ' . $ubicacion->area_nombre : '') .
                                    ($ubicacion->descripcion ? ' / ' . $ubicacion->descripcion : '')
                                );
                            @endphp
                            <option value="{{ $ubicacion->id }}" {{ old('ubicacion_verificada_id') == $ubicacion->id ? 'selected' : '' }}>
                                {{ $ubicacionLabel ?: 'Ubicación ' . $ubicacion->id }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="ui-checkbox ui-field-wide">
                    <input type="checkbox" name="actualizar_ubicacion" value="1" {{ old('actualizar_ubicacion') ? 'checked' : '' }}>
                    <span>Actualizar ubicación actual del activo con la ubicación verificada</span>
                </label>

                <label class="ui-field-wide">
                    <span>Observaciones</span>
                    <textarea name="observaciones" placeholder="Ej. Activo localizado en ubicación distinta, requiere revisión, etiqueta dañada">{{ old('observaciones') }}</textarea>
                </label>
            </div>

            <div class="action-group" style="margin-top:14px">
                <button class="tab" type="submit">Registrar inventario</button>
                <a class="tab" href="{{ route('ubicacion') }}">Limpiar</a>
            </div>
        </form>
    </div>
</section>

<section class="card" style="margin-top:20px">
    <div class="section-title">
        <h2>Filtros de consulta</h2>
        <span class="pill ok">Paginación real</span>
    </div>

    <form method="GET" action="{{ route('ubicacion') }}" class="ui-filter">
        <div class="query-grid query-grid-four">
            <label>
                <span>Número de activo</span>
                <input name="numero_activo" value="{{ $filtros['numero_activo'] ?? '' }}" placeholder="Ej. BIM-537028">
            </label>

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
                <span>Ubicación actual</span>
                <select name="ubicacion_id">
                    <option value="">Todas</option>
                    @foreach ($catalogos['ubicaciones'] as $ubicacion)
                        @php
                            $ubicacionSeleccionada = (string) ($filtros['ubicacion_id'] ?? '');
                            $ubicacionLabel = trim(
                                ($ubicacion->codigo_interno ? $ubicacion->codigo_interno . ' - ' : '') .
                                ($ubicacion->descripcion ?? '')
                            );
                        @endphp
                        <option value="{{ $ubicacion->id }}" {{ $ubicacionSeleccionada === (string) $ubicacion->id ? 'selected' : '' }}>
                            {{ $ubicacionLabel ?: 'Ubicación ' . $ubicacion->id }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="query-grid query-grid-four" style="margin-top:10px">
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
                <span>Estatus operativo</span>
                @php
                    $estatusOperativo = $filtros['estatus_operativo'] ?? '';
                @endphp
                <select name="estatus_operativo">
                    <option value="">Todos</option>
                    <option value="en_operacion" {{ $estatusOperativo === 'en_operacion' ? 'selected' : '' }}>En operación</option>
                    <option value="traslado" {{ $estatusOperativo === 'traslado' ? 'selected' : '' }}>Traslado</option>
                    <option value="baja" {{ $estatusOperativo === 'baja' ? 'selected' : '' }}>Baja</option>
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
                <span>Fecha inventario desde</span>
                <input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
            </label>

            <label>
                <span>Fecha inventario hasta</span>
                <input type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
            </label>

            <label>
                <span>Acciones</span>
                <div class="action-group">
                    <button class="tab" type="submit">Consultar</button>
                    <button class="tab" type="submit" name="export" value="csv">Exportar CSV</button>
                </div>
            </label>

            <label>
                <span>Limpiar</span>
                <div class="action-group">
                    <a class="tab" href="{{ route('ubicacion') }}">Limpiar filtros</a>
                </div>
            </label>
        </div>
    </form>
</section>

<section class="card table-card" style="margin-top:20px">
    <div class="section-title">
        <h2>Consulta de ubicación e inventario</h2>
        <span class="pill ok">Conectado a MySQL</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Activo</th>
                <th>Ubicación actual</th>
                <th>Responsable</th>
                <th>Último inventario</th>
                <th>Estatus</th>
                <th>Último movimiento</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($resultados as $row)
                <tr>
                    <td>
                        <strong>{{ $row->numero_activo }}</strong><br>
                        <small>{{ $row->activo_descripcion }}</small>
                    </td>

                    <td>
                        @php
                            $ubicacionActual = array_filter([
                                $row->ubicacion_codigo,
                                $row->ubicacion_descripcion,
                                $row->edificio,
                                $row->piso,
                                $row->pasillo,
                            ]);
                        @endphp

                        {{ $ubicacionActual ? implode(' / ', $ubicacionActual) : 'Sin ubicación' }}<br>
                        <small>{{ $row->planta_nombre ?? 'Sin planta' }} · {{ $row->area_nombre ?? 'Sin área' }}</small>
                    </td>

                    <td>
                        {{ $row->responsable_nombre ?? 'Sin responsable' }}<br>
                        <small>{{ $row->responsable_correo ?? '' }}</small>
                    </td>

                    <td>
                        {{ $row->fecha_inventario ?? 'Sin inventario' }}<br>
                        <small>{{ $row->inventario_observaciones ?? '' }}</small>
                    </td>

                    <td>
                        @php
                            $estatusFila = $row->estatus_localizacion ?? 'sin_inventario';

                            if ($estatusFila === 'localizado') {
                                $pillClass = 'ok';
                            } elseif ($estatusFila === 'pendiente' || $estatusFila === 'diferencia') {
                                $pillClass = 'warn';
                            } else {
                                $pillClass = 'danger';
                            }

                            $estatusTexto = [
                                'localizado' => 'Localizado',
                                'no_encontrado' => 'No encontrado',
                                'diferencia' => 'Diferencia',
                                'pendiente' => 'Pendiente',
                                'sin_inventario' => 'Sin inventario',
                            ][$estatusFila] ?? ucfirst($estatusFila);
                        @endphp

                        <span class="pill {{ $pillClass }}">{{ $estatusTexto }}</span>
                    </td>

                    <td>
                        {{ $row->fecha_movimiento ?? 'Sin movimiento' }}<br>
                        <small>{{ $row->movimiento_motivo ?? '' }}</small>
                    </td>

                    <td>
                        <div class="table-actions">
                            <a href="{{ route('expediente') }}">Consultar</a>
                            <a href="{{ route('ubicacion', ['numero_activo' => $row->numero_activo]) }}">Filtrar</a>
                            <a href="{{ route('busqueda', ['numero_activo' => $row->numero_activo]) }}">Buscar</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        No existen activos con los criterios seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

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
            <span>M02 ubicación e inventario funcional</span>
        </div>
    </div>
</section>

@endsection
