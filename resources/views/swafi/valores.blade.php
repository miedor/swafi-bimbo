@extends('layouts.app')

@section('title', 'Valores fiscales y financieros | SWAFI')
@section('page_title', 'Valores fiscales y financieros')
@section('page_subtitle', 'Consulta, registro y control de datos contables del activo fijo')
@section('breadcrumb', 'Valores fiscales y financieros')

@section('page_styles')
<style>
    .vf-grid {
        display: grid;
        grid-template-columns: 0.85fr 1.15fr;
        gap: 18px;
        align-items: start;
    }

    .vf-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .vf-field-wide {
        grid-column: 1 / -1;
    }

    .vf-form-grid label,
    .vf-filter label,
    .vf-import-box label {
        display: block;
    }

    .vf-form-grid span,
    .vf-filter span,
    .vf-import-box span {
        display: block;
        margin-bottom: 5px;
        color: #1d3558;
        font-size: 12px;
        font-weight: 900;
    }

    .vf-form-grid input,
    .vf-form-grid select,
    .vf-filter input,
    .vf-filter select {
        width: 100%;
        min-height: 38px;
        padding: 8px 10px;
        border: 1px solid #d5e1ef;
        border-radius: 11px;
        background: #ffffff;
        color: #16304d;
        font-size: 13px;
    }

    .vf-message {
        margin-bottom: 14px;
        padding: 11px 13px;
        border-radius: 13px;
        font-size: 13px;
        font-weight: 700;
    }

    .vf-message-success {
        background: #e8f7ea;
        color: #1f6b2a;
        border: 1px solid #b9e5bf;
    }

    .vf-message-error {
        background: #fdeaea;
        color: #8a1f1f;
        border: 1px solid #f2baba;
    }

    .vf-message ul {
        margin: 6px 0 0 18px;
    }

    .vf-kpi-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-top: 12px;
    }

    .vf-kpi {
        padding: 12px;
        border: 1px solid #e1eaf6;
        border-radius: 15px;
        background: #f8fbff;
    }

    .vf-kpi strong {
        display: block;
        color: #12345a;
        font-size: 16px;
        font-weight: 900;
    }

    .vf-kpi span {
        color: #64748b;
        font-size: 12px;
    }

    .vf-filter {
        margin-bottom: 16px;
        padding: 14px;
        border: 1px solid #e1eaf6;
        border-radius: 18px;
        background: #f8fbff;
    }

    .vf-import-box {
        margin-top: 14px;
        padding: 14px;
        border: 1px dashed #b8cbe4;
        border-radius: 16px;
        background: #f8fbff;
    }

    .vf-import-box h3 {
        margin: 0 0 6px;
        color: #12345a;
        font-size: 15px;
        font-weight: 900;
    }

    .vf-import-box p {
        margin: 0 0 12px;
        color: #64748b;
        font-size: 12px;
        line-height: 1.35;
    }

    .vf-import-box input[type="file"] {
        width: 100%;
        min-height: 42px;
        padding: 9px;
        border: 1px solid #d5e1ef;
        border-radius: 11px;
        background: #ffffff;
        color: #16304d;
        font-size: 13px;
    }

    .vf-import-box input[type="file"]::file-selector-button {
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

    .vf-import-box input[type="file"]::file-selector-button:hover {
        background: #103f7d;
    }

    @media (max-width: 1100px) {
        .vf-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 760px) {
        .vf-form-grid,
        .query-grid-four,
        .vf-kpi-grid {
            grid-template-columns: 1fr !important;
        }
    }
</style>
@endsection

@section('content')

@if (session('success'))
    <div class="vf-message vf-message-success">
        {{ session('success') }}
    </div>
@endif

@if (session('import_summary'))
    @php
        $summary = session('import_summary');
    @endphp

    <div class="vf-message vf-message-success">
        <strong>Resumen de carga masiva:</strong><br>
        Procesados: {{ $summary['procesados'] ?? 0 }} |
        Insertados: {{ $summary['insertados'] ?? 0 }} |
        Actualizados: {{ $summary['actualizados'] ?? 0 }} |
        Rechazados: {{ $summary['rechazados'] ?? 0 }}

        @if (!empty($summary['errores']))
            <ul>
                @foreach (array_slice($summary['errores'], 0, 10) as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif

@if ($errors->any())
    <div class="vf-message vf-message-error">
        <strong>Se encontraron errores:</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<section class="vf-grid">
    <div class="card">
        <div class="section-title">
            <h2>{{ $valorEdit ? 'Editar valores del activo' : 'Registrar valores del activo' }}</h2>
            <span class="pill ok">M02 Control activo</span>
        </div>

        <form method="POST" action="{{ route('valores.store') }}">
            @csrf

            @if ($valorEdit)
                <input type="hidden" name="valor_id" value="{{ $valorEdit->valor_id }}">
            @endif

            <div class="vf-form-grid">
                <label class="vf-field-wide">
                    <span>Activo fijo</span>
                    <select name="numero_activo" required>
                        <option value="">Seleccione...</option>
                        @foreach ($catalogos['activos'] as $activo)
                            @php
                                $activoSeleccionado = old('numero_activo', $valorEdit->numero_activo ?? '');
                            @endphp
                            <option value="{{ $activo->numero_activo }}" {{ $activoSeleccionado === $activo->numero_activo ? 'selected' : '' }}>
                                {{ $activo->numero_activo }} - {{ $activo->descripcion }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Valor fiscal</span>
                    <input
                        type="number"
                        step="0.01"
                        name="valor_fiscal"
                        value="{{ old('valor_fiscal', $valorEdit->valor_fiscal ?? '') }}"
                        placeholder="0.00"
                        required
                    >
                </label>

                <label>
                    <span>Valor financiero</span>
                    <input
                        type="number"
                        step="0.01"
                        name="valor_financiero"
                        value="{{ old('valor_financiero', $valorEdit->valor_financiero ?? '') }}"
                        placeholder="0.00"
                        required
                    >
                </label>

                <label>
                    <span>Depreciación acumulada</span>
                    <input
                        type="number"
                        step="0.01"
                        name="depreciacion_acumulada"
                        value="{{ old('depreciacion_acumulada', $valorEdit->depreciacion_acumulada ?? '0.00') }}"
                        placeholder="0.00"
                        required
                    >
                </label>

                <label>
                    <span>Valor en libros</span>
                    <input
                        type="number"
                        step="0.01"
                        name="valor_en_libros"
                        value="{{ old('valor_en_libros', $valorEdit->valor_en_libros ?? '') }}"
                        placeholder="Se calcula si se deja vacío"
                    >
                </label>

                <label>
                    <span>Vida útil meses</span>
                    <input
                        type="number"
                        name="vida_util_meses"
                        value="{{ old('vida_util_meses', $valorEdit->vida_util_meses ?? '') }}"
                        placeholder="Ej. 120"
                    >
                </label>

                <label>
                    <span>Fecha de corte</span>
                    <input
                        type="date"
                        name="fecha_corte"
                        value="{{ old('fecha_corte', isset($valorEdit->fecha_corte) ? \Illuminate\Support\Carbon::parse($valorEdit->fecha_corte)->format('Y-m-d') : now()->format('Y-m-d')) }}"
                        required
                    >
                </label>

                <label class="vf-field-wide">
                    <span>Estatus contable</span>
                    @php
                        $estatusSeleccionado = old('estatus_contable', $valorEdit->estatus_contable ?? 'vigente');
                    @endphp
                    <select name="estatus_contable" required>
                        <option value="vigente" {{ $estatusSeleccionado === 'vigente' ? 'selected' : '' }}>Vigente</option>
                        <option value="en_revision" {{ $estatusSeleccionado === 'en_revision' ? 'selected' : '' }}>En revisión</option>
                        <option value="baja" {{ $estatusSeleccionado === 'baja' ? 'selected' : '' }}>Baja</option>
                    </select>
                </label>
            </div>

            <div class="action-group" style="margin-top:14px">
                <button class="tab" type="submit">{{ $valorEdit ? 'Actualizar valores' : 'Guardar valores' }}</button>
                <a class="tab" href="{{ route('valores') }}">Limpiar</a>
            </div>
        </form>

        <div class="vf-kpi-grid">
            <div class="vf-kpi">
                <strong>{{ $resultados->total() }}</strong>
                <span>Registros filtrados</span>
            </div>

            <div class="vf-kpi">
                <strong>{{ $catalogos['activos']->count() }}</strong>
                <span>Activos disponibles</span>
            </div>

            <div class="vf-kpi">
                <strong>CSV</strong>
                <span>Exportación activa</span>
            </div>
        </div>

        <div class="vf-import-box">
            <h3>Carga masiva de valores</h3>
            <p>
                Importa valores fiscales y financieros desde un archivo CSV. Si el activo y la fecha de corte ya existen,
                SWAFI actualizará el registro; si no existen, creará uno nuevo.
            </p>

            <form method="POST" action="{{ url('/valores-fiscales-financieros/importar') }}" enctype="multipart/form-data">
                @csrf

                <label>
                    <span>Archivo CSV</span>
                    <input type="file" name="archivo_csv" accept=".csv,.txt" required>
                </label>

                <div class="action-group" style="margin-top:12px">
                    <button class="tab" type="submit">Importar CSV</button>
                    <a class="tab" href="{{ url('/valores-fiscales-financieros/plantilla-csv') }}">Descargar plantilla</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="section-title">
            <h2>Filtros de consulta</h2>
            <span class="pill ok">Paginación real</span>
        </div>

        <form method="GET" action="{{ route('valores') }}" class="vf-filter">
            <div class="query-grid query-grid-four">
                <label>
                    <span>Número de activo</span>
                    <input name="numero_activo" value="{{ $filtros['numero_activo'] ?? '' }}" placeholder="Ej. BIM-000001">
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
                                {{ $centro->clave }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="query-grid query-grid-four" style="margin-top:10px">
                <label>
                    <span>Tipo de activo</span>
                    <select name="tipo_activo_id">
                        <option value="">Todos</option>
                        @foreach ($catalogos['tiposActivo'] as $tipo)
                            @php
                                $tipoSeleccionado = (string) ($filtros['tipo_activo_id'] ?? '');
                            @endphp
                            <option value="{{ $tipo->id }}" {{ $tipoSeleccionado === (string) $tipo->id ? 'selected' : '' }}>
                                {{ $tipo->descripcion }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Fecha desde</span>
                    <input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
                </label>

                <label>
                    <span>Fecha hasta</span>
                    <input type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                </label>

                <label>
                    <span>Estatus</span>
                    @php
                        $filtroEstatus = $filtros['estatus_contable'] ?? '';
                    @endphp
                    <select name="estatus_contable">
                        <option value="">Todos</option>
                        <option value="vigente" {{ $filtroEstatus === 'vigente' ? 'selected' : '' }}>Vigente</option>
                        <option value="en_revision" {{ $filtroEstatus === 'en_revision' ? 'selected' : '' }}>En revisión</option>
                        <option value="baja" {{ $filtroEstatus === 'baja' ? 'selected' : '' }}>Baja</option>
                    </select>
                </label>
            </div>

            <div class="query-grid query-grid-four" style="margin-top:10px">
                <label>
                    <span>Valor desde</span>
                    <input type="number" step="0.01" name="valor_desde" value="{{ $filtros['valor_desde'] ?? '' }}">
                </label>

                <label>
                    <span>Valor hasta</span>
                    <input type="number" step="0.01" name="valor_hasta" value="{{ $filtros['valor_hasta'] ?? '' }}">
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

                <label>
                    <span>Acción</span>
                    <div class="action-group">
                        <button class="tab" type="submit">Consultar</button>
                        <button class="tab" type="submit" name="export" value="csv">Exportar CSV</button>
                    </div>
                </label>
            </div>

            <div class="action-group" style="margin-top:12px">
                <a class="tab" href="{{ route('valores') }}">Limpiar filtros</a>
            </div>
        </form>
    </div>
</section>

<section class="card table-card" style="margin-top:20px">
    <div class="section-title">
        <h2>Valores fiscales y financieros registrados</h2>
        <span class="pill ok">Conectado a MySQL</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Activo</th>
                <th>Proveedor / Planta</th>
                <th>Valor fiscal</th>
                <th>Depreciación</th>
                <th>Valor en libros</th>
                <th>Valor financiero</th>
                <th>Estatus</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($resultados as $row)
                <tr>
                    <td>{{ $row->folio_factura ?? 'Sin folio' }}</td>

                    <td>
                        <strong>{{ $row->numero_activo }}</strong><br>
                        <small>{{ $row->activo_descripcion }}</small>
                    </td>

                    <td>
                        {{ $row->proveedor_nombre ?? 'Sin proveedor' }}<br>
                        <small>{{ $row->planta_nombre ?? 'Sin planta' }} · {{ $row->centro_costo_clave ?? 'Sin CC' }}</small>
                    </td>

                    <td>$ {{ number_format((float) $row->valor_fiscal, 2) }}</td>
                    <td>$ {{ number_format((float) $row->depreciacion_acumulada, 2) }}</td>
                    <td>$ {{ number_format((float) $row->valor_en_libros, 2) }}</td>
                    <td>$ {{ number_format((float) $row->valor_financiero, 2) }}</td>

                    <td>
                        @php
                            $estatusFila = $row->estatus_contable ?? 'vigente';

                            if ($estatusFila === 'vigente') {
                                $pillClass = 'ok';
                            } elseif ($estatusFila === 'en_revision') {
                                $pillClass = 'warn';
                            } else {
                                $pillClass = 'danger';
                            }
                        @endphp

                        <span class="pill {{ $pillClass }}">
                            {{ ucfirst(str_replace('_', ' ', $estatusFila)) }}
                        </span>
                    </td>

                    <td>
                        <div class="table-actions">
                            <a href="{{ route('expediente') }}">Consultar</a>

                            <a href="{{ route('valores', array_merge(request()->query(), ['editar_valor' => $row->valor_id])) }}">
                                Editar
                            </a>

                            <form
                                method="POST"
                                action="{{ route('valores.destroy', $row->valor_id) }}"
                                onsubmit="return confirm('¿Deseas eliminar este registro de valores?');"
                                style="display:inline"
                            >
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    style="border:0;background:none;color:#b42318;font-weight:800;cursor:pointer;padding:0"
                                >
                                    Eliminar
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">
                        No existen valores registrados con los criterios seleccionados.
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
            <span>M02 funcional con paginación</span>
        </div>
    </div>
</section>

@endsection
