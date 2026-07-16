@extends('layouts.app')

@section('title', 'Catálogos base | SWAFI')
@section('page_title', 'Catálogos base')
@section('page_subtitle', 'Administración funcional de catálogos transversales del sistema')
@section('breadcrumb', 'Catálogos base')

@section('page_styles')
<style>
    .cat-grid {
        display: grid;
        grid-template-columns: 0.9fr 1.1fr;
        gap: 18px;
        align-items: start;
    }

    .cat-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .cat-field-wide {
        grid-column: 1 / -1;
    }

    .cat-form-grid label,
    .cat-filter label,
    .cat-import label {
        display: block;
    }

    .cat-form-grid span,
    .cat-filter span,
    .cat-import span {
        display: block;
        margin-bottom: 5px;
        color: #1d3558;
        font-size: 12px;
        font-weight: 900;
    }

    .cat-form-grid input,
    .cat-form-grid select,
    .cat-filter input,
    .cat-filter select,
    .cat-import input[type="file"] {
        width: 100%;
        min-height: 38px;
        padding: 8px 10px;
        border: 1px solid #d5e1ef;
        border-radius: 11px;
        background: #ffffff;
        color: #16304d;
        font-size: 13px;
    }

    .cat-import input[type="file"]::file-selector-button {
        margin-right: 12px;
        padding: 8px 13px;
        border: 0;
        border-radius: 10px;
        background: #154f9b;
        color: #ffffff;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
    }

    .cat-message {
        margin-bottom: 14px;
        padding: 11px 13px;
        border-radius: 13px;
        font-size: 13px;
        font-weight: 700;
    }

    .cat-message-success {
        background: #e8f7ea;
        color: #1f6b2a;
        border: 1px solid #b9e5bf;
    }

    .cat-message-error {
        background: #fdeaea;
        color: #8a1f1f;
        border: 1px solid #f2baba;
    }

    .cat-message ul {
        margin: 6px 0 0 18px;
    }

    .cat-filter,
    .cat-import {
        padding: 14px;
        border: 1px solid #e1eaf6;
        border-radius: 18px;
        background: #f8fbff;
    }

    .cat-import {
        margin-top: 16px;
        border-style: dashed;
    }

    .cat-import h3 {
        margin: 0 0 6px;
        color: #12345a;
        font-size: 15px;
        font-weight: 900;
    }

    .cat-import p {
        margin: 0 0 12px;
        color: #64748b;
        font-size: 12px;
        line-height: 1.35;
    }

    .cat-kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-top: 14px;
    }

    .cat-kpi {
        padding: 12px;
        border: 1px solid #e1eaf6;
        border-radius: 15px;
        background: #f8fbff;
    }

    .cat-kpi strong {
        display: block;
        color: #12345a;
        font-size: 18px;
        font-weight: 900;
    }

    .cat-kpi span {
        color: #64748b;
        font-size: 12px;
    }

    .cat-help {
        margin-top: 12px;
        padding: 10px 12px;
        border-radius: 13px;
        background: #eef6ff;
        color: #385b82;
        font-size: 12px;
        line-height: 1.4;
    }

    .cat-table-scroll {
        width: 100%;
        overflow-x: auto;
    }

    @media (max-width: 1100px) {
        .cat-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 760px) {
        .cat-form-grid,
        .query-grid-four,
        .cat-kpi-grid {
            grid-template-columns: 1fr !important;
        }
    }
</style>
@endsection

@section('content')

@if (session('success'))
    <div class="cat-message cat-message-success">
        {{ session('success') }}
    </div>
@endif

@if (session('import_summary'))
    @php
        $summary = session('import_summary');
    @endphp

    <div class="cat-message cat-message-success">
        <strong>Resumen de carga masiva de {{ $summary['catalogo'] ?? 'catálogo' }}:</strong><br>
        Procesados: {{ $summary['procesados'] ?? 0 }} |
        Insertados: {{ $summary['insertados'] ?? 0 }} |
        Actualizados: {{ $summary['actualizados'] ?? 0 }} |
        Rechazados: {{ $summary['rechazados'] ?? 0 }}

        @if (!empty($summary['errores']))
            <ul>
                @foreach (array_slice($summary['errores'], 0, 12) as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif

@if ($errors->any())
    <div class="cat-message cat-message-error">
        <strong>Se encontraron errores:</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $editing = $registroEdit !== null;
    $estatusActual = old('estatus', $registroEdit->estatus ?? 'activo');
@endphp

<section class="cat-grid">
    <div class="card">
        <div class="section-title">
            <h2>{{ $editing ? 'Editar catálogo' : 'Alta de catálogo' }}</h2>
            <span class="pill ok">M04 funcional</span>
        </div>

        <form method="POST" action="{{ route('catalogos.store') }}">
            @csrf

            <input type="hidden" name="catalogo" value="{{ $catalogoActivo }}">

            @if ($editing)
                <input type="hidden" name="id" value="{{ $registroEdit->id }}">
            @endif

            <div class="cat-form-grid">
                <label class="cat-field-wide">
                    <span>Tipo de catálogo</span>
                    <select onchange="window.location='{{ route('catalogos') }}?catalogo=' + this.value">
                        @foreach ($catalogosDisponibles as $key => $label)
                            <option value="{{ $key }}" {{ $catalogoActivo === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                @if ($catalogoActivo === 'proveedores')
                    <label>
                        <span>RFC</span>
                        <input name="rfc" value="{{ old('rfc', $registroEdit->rfc ?? '') }}" maxlength="13" required>
                    </label>

                    <label>
                        <span>Nombre</span>
                        <input name="nombre" value="{{ old('nombre', $registroEdit->nombre ?? '') }}" required>
                    </label>

                    <label>
                        <span>Correo</span>
                        <input type="email" name="correo" value="{{ old('correo', $registroEdit->correo ?? '') }}">
                    </label>

                    <label>
                        <span>Teléfono</span>
                        <input name="telefono" value="{{ old('telefono', $registroEdit->telefono ?? '') }}">
                    </label>
                @endif

                @if ($catalogoActivo === 'plantas')
                    <label>
                        <span>Clave</span>
                        <input name="clave" value="{{ old('clave', $registroEdit->clave ?? '') }}" required>
                    </label>

                    <label>
                        <span>Nombre</span>
                        <input name="nombre" value="{{ old('nombre', $registroEdit->nombre ?? '') }}" required>
                    </label>

                    <label>
                        <span>Estado</span>
                        <input name="estado" value="{{ old('estado', $registroEdit->estado ?? '') }}">
                    </label>

                    <label>
                        <span>País</span>
                        <input name="pais" value="{{ old('pais', $registroEdit->pais ?? 'México') }}" required>
                    </label>
                @endif

                @if ($catalogoActivo === 'centros_costo')
                    <label>
                        <span>Clave</span>
                        <input name="clave" value="{{ old('clave', $registroEdit->clave ?? '') }}" required>
                    </label>

                    <label>
                        <span>Descripción</span>
                        <input name="descripcion" value="{{ old('descripcion', $registroEdit->descripcion ?? '') }}" required>
                    </label>
                @endif

                @if ($catalogoActivo === 'tipos_activo')
                    <label>
                        <span>Clave</span>
                        <input name="clave" value="{{ old('clave', $registroEdit->clave ?? '') }}" required>
                    </label>

                    <label>
                        <span>Descripción</span>
                        <input name="descripcion" value="{{ old('descripcion', $registroEdit->descripcion ?? '') }}" required>
                    </label>

                    <label>
                        <span>Vida útil meses</span>
                        <input type="number" name="vida_util_meses" value="{{ old('vida_util_meses', $registroEdit->vida_util_meses ?? '') }}">
                    </label>
                @endif

                @if ($catalogoActivo === 'areas')
                    <label>
                        <span>Planta</span>
                        <select name="planta_id" required>
                            <option value="">Seleccione...</option>
                            @foreach ($opciones['plantas'] as $planta)
                                @php
                                    $plantaSeleccionada = (string) old('planta_id', $registroEdit->planta_id ?? '');
                                @endphp
                                <option value="{{ $planta->id }}" {{ $plantaSeleccionada === (string) $planta->id ? 'selected' : '' }}>
                                    {{ $planta->clave }} - {{ $planta->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Nombre del área</span>
                        <input name="nombre" value="{{ old('nombre', $registroEdit->nombre ?? '') }}" required>
                    </label>
                @endif

                @if ($catalogoActivo === 'ubicaciones')
                    <label>
                        <span>Planta</span>
                        <select name="planta_id" required>
                            <option value="">Seleccione...</option>
                            @foreach ($opciones['plantas'] as $planta)
                                @php
                                    $plantaSeleccionada = (string) old('planta_id', $registroEdit->planta_id ?? '');
                                @endphp
                                <option value="{{ $planta->id }}" {{ $plantaSeleccionada === (string) $planta->id ? 'selected' : '' }}>
                                    {{ $planta->clave }} - {{ $planta->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Área</span>
                        <select name="area_id">
                            <option value="">Sin área</option>
                            @foreach ($opciones['areas'] as $area)
                                @php
                                    $areaSeleccionada = (string) old('area_id', $registroEdit->area_id ?? '');
                                @endphp
                                <option value="{{ $area->id }}" {{ $areaSeleccionada === (string) $area->id ? 'selected' : '' }}>
                                    {{ $area->planta_nombre }} / {{ $area->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Código interno</span>
                        <input name="codigo_interno" value="{{ old('codigo_interno', $registroEdit->codigo_interno ?? '') }}">
                    </label>

                    <label>
                        <span>Edificio</span>
                        <input name="edificio" value="{{ old('edificio', $registroEdit->edificio ?? '') }}">
                    </label>

                    <label>
                        <span>Piso</span>
                        <input name="piso" value="{{ old('piso', $registroEdit->piso ?? '') }}">
                    </label>

                    <label>
                        <span>Pasillo</span>
                        <input name="pasillo" value="{{ old('pasillo', $registroEdit->pasillo ?? '') }}">
                    </label>

                    <label class="cat-field-wide">
                        <span>Descripción</span>
                        <input name="descripcion" value="{{ old('descripcion', $registroEdit->descripcion ?? '') }}">
                    </label>
                @endif

                @if ($catalogoActivo === 'responsables')
                    <label>
                        <span>Nombre</span>
                        <input name="nombre" value="{{ old('nombre', $registroEdit->nombre ?? '') }}" required>
                    </label>

                    <label>
                        <span>Correo</span>
                        <input type="email" name="correo" value="{{ old('correo', $registroEdit->correo ?? '') }}">
                    </label>

                    <label>
                        <span>Teléfono</span>
                        <input name="telefono" value="{{ old('telefono', $registroEdit->telefono ?? '') }}">
                    </label>
                @endif

                <label>
                    <span>Estatus</span>
                    <select name="estatus" required>
                        <option value="activo" {{ $estatusActual === 'activo' ? 'selected' : '' }}>Activo</option>
                        <option value="inactivo" {{ $estatusActual === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                    </select>
                </label>
            </div>

            <div class="action-group" style="margin-top:14px">
                <button class="tab" type="submit">
                    {{ $editing ? 'Actualizar registro' : 'Guardar registro' }}
                </button>
                <a class="tab" href="{{ route('catalogos', ['catalogo' => $catalogoActivo]) }}">Limpiar</a>
            </div>
        </form>

        <div class="cat-import">
            <h3>Carga masiva del catálogo actual</h3>
            <p>
                Importa registros desde un archivo CSV. Si la clave principal ya existe, SWAFI actualizará el registro;
                si no existe, lo creará como nuevo.
            </p>

            <form method="POST" action="{{ route('catalogos.importar') }}" enctype="multipart/form-data">
                @csrf

                <input type="hidden" name="catalogo" value="{{ $catalogoActivo }}">

                <label>
                    <span>Archivo CSV</span>
                    <input type="file" name="archivo_csv" accept=".csv,.txt" required>
                </label>

                <div class="cat-help">
                    Encabezados esperados para este catálogo:
                    <strong>{{ implode(', ', $headersLayout) }}</strong>.
                    Guarda el archivo desde Excel como CSV UTF-8.
                </div>

                <div class="action-group" style="margin-top:12px">
                    <button class="tab" type="submit">Importar catálogo</button>
                    <a class="tab" href="{{ route('catalogos.plantilla', ['catalogo' => $catalogoActivo]) }}">
                        Descargar plantilla
                    </a>
                </div>
            </form>
        </div>

        <div class="cat-help">
            La eliminación se maneja como <strong>desactivación</strong> para conservar trazabilidad y evitar borrar catálogos
            ya relacionados con activos, expedientes, ubicaciones o reportes.
        </div>
    </div>

    <div class="card">
        <div class="section-title">
            <h2>Resumen del catálogo</h2>
            <span class="pill ok">Conectado a MySQL</span>
        </div>

        <div class="cat-kpi-grid">
            <div class="cat-kpi">
                <strong>{{ number_format((int) $kpis['total']) }}</strong>
                <span>Registros totales</span>
            </div>

            <div class="cat-kpi">
                <strong>{{ number_format((int) $kpis['activos']) }}</strong>
                <span>Activos</span>
            </div>

            <div class="cat-kpi">
                <strong>{{ number_format((int) $kpis['inactivos']) }}</strong>
                <span>Inactivos</span>
            </div>

            <div class="cat-kpi">
                <strong>CSV</strong>
                <span>Importación / exportación</span>
            </div>
        </div>

        <div class="quick-links" style="margin-top:14px">
            @foreach ($catalogosDisponibles as $key => $label)
                <a href="{{ route('catalogos', ['catalogo' => $key]) }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="cat-help">
            Catálogo actual: <strong>{{ $catalogosDisponibles[$catalogoActivo] ?? 'Catálogo' }}</strong>.
            Estos datos alimentan registro individual, registro masivo, ubicación, valores, búsqueda avanzada y reportes.
        </div>
    </div>
</section>

<div data-swafi-query-workspace data-swafi-query-key="catalogos">
<section class="card" style="margin-top:20px" data-swafi-query-panel>
    <div class="section-title">
        <h2>Filtros de consulta</h2>
        <span class="pill ok">Paginación real</span>
    </div>

    <form method="GET" action="{{ route('catalogos') }}" class="cat-filter" data-swafi-query-form>
        <input type="hidden" name="catalogo" value="{{ $catalogoActivo }}">

        <div class="query-grid query-grid-four">
            <label>
                <span>Buscar</span>
                <input name="buscar" value="{{ $filtros['buscar'] ?? '' }}" placeholder="Clave, nombre, RFC, correo, ubicación">
            </label>

            <label>
                <span>Estatus</span>
                @php
                    $filtroEstatus = $filtros['estatus'] ?? '';
                @endphp
                <select name="estatus">
                    <option value="">Todos</option>
                    <option value="activo" {{ $filtroEstatus === 'activo' ? 'selected' : '' }}>Activo</option>
                    <option value="inactivo" {{ $filtroEstatus === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                </select>
            </label>

            <label>
                <span>Planta</span>
                <select name="planta_id">
                    <option value="">Todas</option>
                    @foreach ($opciones['plantas'] as $planta)
                        @php
                            $filtroPlanta = (string) ($filtros['planta_id'] ?? '');
                        @endphp
                        <option value="{{ $planta->id }}" {{ $filtroPlanta === (string) $planta->id ? 'selected' : '' }}>
                            {{ $planta->clave }} - {{ $planta->nombre }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Área</span>
                <select name="area_id">
                    <option value="">Todas</option>
                    @foreach ($opciones['areas'] as $area)
                        @php
                            $filtroArea = (string) ($filtros['area_id'] ?? '');
                        @endphp
                        <option value="{{ $area->id }}" {{ $filtroArea === (string) $area->id ? 'selected' : '' }}>
                            {{ $area->planta_nombre }} / {{ $area->nombre }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="query-grid query-grid-four" style="margin-top:10px">
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

            <label>
                <span>Consultar</span>
                <div class="action-group">
                    <button class="tab" type="submit">Consultar</button>
                    <button class="tab" type="submit" name="export" value="csv">Exportar CSV</button>
                </div>
            </label>

            <label>
                <span>Limpiar</span>
                <div class="action-group">
                    <a class="tab" href="{{ route('catalogos', ['catalogo' => $catalogoActivo]) }}">Limpiar filtros</a>
                </div>
            </label>
        </div>
    </form>
</section>

<section class="card table-card" style="margin-top:20px" data-swafi-query-results id="swafi-catalogos-resultados">
    <div class="section-title">
        <h2>Consulta de {{ $catalogosDisponibles[$catalogoActivo] ?? 'catálogo' }}</h2>
        <span class="pill ok">CRUD + carga masiva</span>
    </div>

    <div class="cat-table-scroll">
        <table>
            <thead>
                <tr>
                    @foreach ($columnas as $label)
                        <th>{{ $label }}</th>
                    @endforeach
                    <th>Actualizado</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($resultados as $row)
                    <tr>
                        @foreach ($columnas as $key => $label)
                            <td>
                                @if ($key === 'estatus')
                                    @php
                                        $estatusFila = $row->estatus ?? 'activo';
                                        $pillClass = $estatusFila === 'activo' ? 'ok' : 'warn';
                                    @endphp

                                    <span class="pill {{ $pillClass }}">
                                        {{ ucfirst($estatusFila) }}
                                    </span>
                                @else
                                    {{ data_get($row, $key) !== null && data_get($row, $key) !== '' ? data_get($row, $key) : '—' }}
                                @endif
                            </td>
                        @endforeach

                        <td>{{ $row->updated_at ?? '—' }}</td>

                        <td>
                            <div class="table-actions">
                                <a href="{{ route('catalogos', array_merge(request()->query(), ['catalogo' => $catalogoActivo, 'editar' => $row->id])) }}">
                                    Editar
                                </a>

                                <form
                                    method="POST"
                                    action="{{ route('catalogos.destroy', [$catalogoActivo, $row->id]) }}"
                                    onsubmit="return confirm('¿Deseas desactivar este registro del catálogo?');"
                                    style="display:inline"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        style="border:0;background:none;color:#b42318;font-weight:800;cursor:pointer;padding:0"
                                    >
                                        Desactivar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columnas) + 2 }}">
                            No existen registros con los criterios seleccionados.
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
            <span>M04 catálogos funcional</span>
        </div>
    </div>
</section>
</div>

@endsection
