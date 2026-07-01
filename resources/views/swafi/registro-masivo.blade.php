@extends('layouts.app')

@section('title', 'Registro masivo | SWAFI')
@section('page_title', 'Registro masivo')
@section('page_subtitle', 'Carga de expedientes mediante layout CSV y validación previa')
@section('breadcrumb', 'Registro masivo')

@section('page_styles')
<style>
    .rm-grid {
        display: grid;
        grid-template-columns: 0.9fr 1.1fr;
        gap: 18px;
        align-items: start;
    }

    .rm-box {
        padding: 14px;
        border: 1px dashed #b8cbe4;
        border-radius: 16px;
        background: #f8fbff;
    }

    .rm-box h3 {
        margin: 0 0 6px;
        color: #12345a;
        font-size: 16px;
        font-weight: 900;
    }

    .rm-box p {
        margin: 0 0 12px;
        color: #64748b;
        font-size: 12px;
        line-height: 1.35;
    }

    .rm-box label,
    .rm-filter label {
        display: block;
    }

    .rm-box span,
    .rm-filter span {
        display: block;
        margin-bottom: 5px;
        color: #1d3558;
        font-size: 12px;
        font-weight: 900;
    }

    .rm-box input[type="file"],
    .rm-filter input,
    .rm-filter select {
        width: 100%;
        min-height: 38px;
        padding: 8px 10px;
        border: 1px solid #d5e1ef;
        border-radius: 11px;
        background: #ffffff;
        color: #16304d;
        font-size: 13px;
    }

    .rm-box input[type="file"] {
        min-height: 42px;
        padding: 9px;
    }

    .rm-box input[type="file"]::file-selector-button {
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

    .rm-message {
        margin-bottom: 14px;
        padding: 11px 13px;
        border-radius: 13px;
        font-size: 13px;
        font-weight: 700;
    }

    .rm-message-success {
        background: #e8f7ea;
        color: #1f6b2a;
        border: 1px solid #b9e5bf;
    }

    .rm-message-error {
        background: #fdeaea;
        color: #8a1f1f;
        border: 1px solid #f2baba;
    }

    .rm-message ul {
        margin: 6px 0 0 18px;
    }

    .rm-kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-top: 14px;
    }

    .rm-kpi {
        padding: 12px;
        border: 1px solid #e1eaf6;
        border-radius: 15px;
        background: #f8fbff;
    }

    .rm-kpi strong {
        display: block;
        color: #12345a;
        font-size: 18px;
        font-weight: 900;
    }

    .rm-kpi span {
        color: #64748b;
        font-size: 12px;
    }

    .rm-filter {
        padding: 14px;
        border: 1px solid #e1eaf6;
        border-radius: 18px;
        background: #f8fbff;
    }

    @media (max-width: 1100px) {
        .rm-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 760px) {
        .query-grid-four,
        .rm-kpi-grid {
            grid-template-columns: 1fr !important;
        }
    }
</style>
@endsection

@section('content')

@if (session('success'))
    <div class="rm-message rm-message-success">
        {{ session('success') }}
    </div>
@endif

@if (session('import_summary'))
    @php
        $summary = session('import_summary');
    @endphp

    <div class="rm-message rm-message-success">
        <strong>Resumen de carga masiva:</strong><br>
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
    <div class="rm-message rm-message-error">
        <strong>Se encontraron errores:</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<section class="rm-grid">
    <div class="card">
        <div class="section-title">
            <h2>Carga masiva por layout</h2>
            <span class="pill ok">CSV validado</span>
        </div>

        <div class="rm-box">
            <h3>Importar expedientes</h3>
            <p>
                Carga un archivo CSV con activos y expedientes. SWAFI validará catálogos, fechas, montos,
                folios, UUID CFDI y referencias PDF/XML antes de registrar la información.
            </p>

            <form method="POST" action="{{ url('/registro-masivo/importar') }}" enctype="multipart/form-data">
                @csrf

                <label>
                    <span>Archivo CSV</span>
                    <input type="file" name="archivo_csv" accept=".csv,.txt" required>
                </label>

                <div class="action-group" style="margin-top:12px">
                    <button class="tab" type="submit">Procesar carga</button>
                    <a class="tab" href="{{ url('/registro-masivo/plantilla-csv') }}">Descargar plantilla</a>
                </div>
            </form>
        </div>

        <div class="rm-kpi-grid">
            <div class="rm-kpi">
                <strong>{{ $resultados->total() }}</strong>
                <span>Expedientes filtrados</span>
            </div>

            <div class="rm-kpi">
                <strong>CSV</strong>
                <span>Layout soportado</span>
            </div>

            <div class="rm-kpi">
                <strong>PDF/XML</strong>
                <span>Referencia documental</span>
            </div>

            <div class="rm-kpi">
                <strong>BIT</strong>
                <span>Bitácora activa</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="section-title">
            <h2>Reglas del layout</h2>
            <span class="pill warn">Validación previa</span>
        </div>

        <div class="list">
            <div class="list-item">
                <strong>Catálogos obligatorios</strong>
                <span>Proveedor RFC, tipo de activo, centro de costo y planta deben existir previamente.</span>
            </div>

            <div class="list-item">
                <strong>Llave de actualización</strong>
                <span>Si ya existe el mismo número de activo y folio de factura, SWAFI actualizará el expediente.</span>
            </div>

            <div class="list-item">
                <strong>Estatus documental</strong>
                <span>Si el layout contiene PDF y XML, el expediente queda completo; si falta alguno, queda incompleto.</span>
            </div>

            <div class="list-item">
                <strong>Errores controlados</strong>
                <span>Las filas con catálogos inexistentes, fechas inválidas, montos inválidos o UUID duplicado se rechazan.</span>
            </div>
        </div>
    </div>
</section>

<section class="card" style="margin-top:20px">
    <div class="section-title">
        <h2>Filtros de consulta</h2>
        <span class="pill ok">Paginación real</span>
    </div>

    <form method="GET" action="{{ route('registro-masivo') }}" class="rm-filter">
        <div class="query-grid query-grid-four">
            <label>
                <span>Número de activo</span>
                <input name="numero_activo" value="{{ $filtros['numero_activo'] ?? '' }}" placeholder="Ej. BIM-537028">
            </label>

            <label>
                <span>Folio factura</span>
                <input name="folio_factura" value="{{ $filtros['folio_factura'] ?? '' }}" placeholder="Ej. FAC-000184">
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
        </div>

        <div class="query-grid query-grid-four" style="margin-top:10px">
            <label>
                <span>Estatus documental</span>
                @php
                    $estatusSeleccionado = $filtros['estatus'] ?? '';
                @endphp
                <select name="estatus">
                    <option value="">Todos</option>
                    <option value="completo" {{ $estatusSeleccionado === 'completo' ? 'selected' : '' }}>Completo</option>
                    <option value="incompleto" {{ $estatusSeleccionado === 'incompleto' ? 'selected' : '' }}>Incompleto</option>
                    <option value="observado" {{ $estatusSeleccionado === 'observado' ? 'selected' : '' }}>Observado</option>
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
                <span>Monto desde</span>
                <input type="number" step="0.01" name="monto_desde" value="{{ $filtros['monto_desde'] ?? '' }}">
            </label>

            <label>
                <span>Monto hasta</span>
                <input type="number" step="0.01" name="monto_hasta" value="{{ $filtros['monto_hasta'] ?? '' }}">
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
                    <a class="tab" href="{{ route('registro-masivo') }}">Limpiar filtros</a>
                </div>
            </label>
        </div>
    </form>
</section>

<section class="card table-card" style="margin-top:20px">
    <div class="section-title">
        <h2>Expedientes registrados por carga o captura</h2>
        <span class="pill ok">Conectado a MySQL</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Activo</th>
                <th>Factura</th>
                <th>Proveedor / Planta</th>
                <th>Monto</th>
                <th>Documentos</th>
                <th>Estatus</th>
                <th>Fecha registro</th>
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
                        {{ $row->folio_factura }}<br>
                        <small>{{ $row->uuid_cfdi ?: 'Sin UUID' }}</small>
                    </td>

                    <td>
                        {{ $row->proveedor_nombre ?? 'Sin proveedor' }}<br>
                        <small>{{ $row->planta_nombre ?? 'Sin planta' }} · {{ $row->centro_costo_clave ?? 'Sin CC' }}</small>
                    </td>

                    <td>
                        $ {{ number_format((float) $row->monto_factura, 2) }} {{ $row->moneda }}
                    </td>

                    <td>
                        PDF: {{ ((int) $row->total_pdf) > 0 ? 'Sí' : 'No' }}<br>
                        <small>XML: {{ ((int) $row->total_xml) > 0 ? 'Sí' : 'No' }}</small>
                    </td>

                    <td>
                        @php
                            $estatusFila = $row->estatus ?? 'incompleto';

                            if ($estatusFila === 'completo') {
                                $pillClass = 'ok';
                            } elseif ($estatusFila === 'observado') {
                                $pillClass = 'warn';
                            } else {
                                $pillClass = 'danger';
                            }
                        @endphp

                        <span class="pill {{ $pillClass }}">
                            {{ ucfirst($estatusFila) }}
                        </span>
                    </td>

                    <td>{{ $row->created_at }}</td>

                    <td>
                        <div class="table-actions">
                            <a href="{{ route('expediente', $row->expediente_id) }}">Consultar</a>
                            <a href="{{ route('busqueda', ['numero_activo' => $row->numero_activo]) }}">Buscar</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        No existen expedientes con los criterios seleccionados.
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
            <span>M01 registro masivo funcional</span>
        </div>
    </div>
</section>

@endsection
