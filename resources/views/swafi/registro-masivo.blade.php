@extends('layouts.app')

@section('title', 'Registro masivo | SWAFI')
@section('page_title', 'Registro masivo')
@section('page_subtitle', 'Carga de expedientes mediante layout CSV, ZIP documental y validación previa')
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

    .rm-help {
        margin-top: 12px;
        padding: 10px 12px;
        border-radius: 13px;
        background: #eef6ff;
        color: #385b82;
        font-size: 12px;
        line-height: 1.4;
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



    .rm-preview-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin: 14px 0;
    }

    .rm-preview-stat {
        padding: 14px;
        border: 1px solid #dbe7f5;
        border-radius: 16px;
        background: #f8fbff;
    }

    .rm-preview-stat strong {
        display: block;
        color: #12345a;
        font-size: 22px;
        font-weight: 900;
    }

    .rm-preview-stat span {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
    }

    .rm-preview-stat.accepted { background: #eefbf2; border-color: #bfe8ca; }
    .rm-preview-stat.observed { background: #fff8e7; border-color: #f1d38a; }
    .rm-preview-stat.rejected { background: #fff0f0; border-color: #f1bcbc; }

    .rm-preview-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: end;
        justify-content: space-between;
        gap: 12px;
        margin: 12px 0;
        padding: 12px;
        border: 1px solid #dbe7f5;
        border-radius: 16px;
        background: #f8fbff;
    }

    .rm-preview-toolbar form {
        margin: 0;
    }

    .rm-preview-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }

    .rm-confirm-box {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #334e70;
        font-size: 12px;
        font-weight: 800;
    }

    .rm-confirm-box input {
        width: 17px;
        height: 17px;
    }

    .rm-issue-list {
        margin: 5px 0 0;
        padding-left: 17px;
        color: #64748b;
        font-size: 11px;
        line-height: 1.35;
    }

    .rm-issue-list.error { color: #9f2424; }
    .rm-issue-list.warning { color: #8a5a00; }

    .rm-batches {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }

    .rm-batch-item {
        display: block;
        padding: 12px;
        border: 1px solid #dbe7f5;
        border-radius: 14px;
        background: #f8fbff;
        color: #17375e;
        text-decoration: none;
    }

    .rm-batch-item strong,
    .rm-batch-item span {
        display: block;
    }

    .rm-batch-item span {
        margin-top: 4px;
        color: #64748b;
        font-size: 11px;
    }

    .rm-rollback-panel {
        margin: 14px 0;
        padding: 16px;
        border: 1px solid #f0c36a;
        border-radius: 16px;
        background: #fff9e9;
    }

    .rm-rollback-panel.is-complete {
        border-color: #b8dfc1;
        background: #eef9f1;
    }

    .rm-rollback-panel h3 {
        margin: 0 0 6px;
        color: #17375e;
        font-size: 16px;
        font-weight: 900;
    }

    .rm-rollback-panel p {
        margin: 0 0 10px;
        color: #536b88;
        font-size: 12px;
        line-height: 1.45;
    }

    .rm-rollback-panel textarea {
        width: 100%;
        min-height: 92px;
        padding: 10px 12px;
        border: 1px solid #d5e1ef;
        border-radius: 12px;
        background: #ffffff;
        color: #17375e;
        font: inherit;
        resize: vertical;
    }

    .rm-rollback-warning {
        padding: 10px 12px;
        border-radius: 12px;
        background: #fff1ce;
        color: #7c4c00;
        font-size: 12px;
        font-weight: 800;
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

        .rm-preview-summary,
        .rm-batches {
            grid-template-columns: 1fr !important;
        }
    }
</style>
@endsection

@section('content')

@php
    $documentaryStatusLabels = collect($catalogos['estatusDocumentales'] ?? [])
        ->pluck('nombre', 'clave')
        ->all();
@endphp

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

@if (session('rollback_summary'))
    @php
        $rollbackSummary = session('rollback_summary');
    @endphp

    <div class="rm-message rm-message-success">
        <strong>Resumen de reversión controlada:</strong><br>
        Filas revertidas: {{ $rollbackSummary['filas_revertidas'] ?? 0 }} |
        Expedientes dados de baja lógica: {{ $rollbackSummary['expedientes_dados_baja'] ?? 0 }} |
        Valores dados de baja lógica: {{ $rollbackSummary['valores_dados_baja'] ?? 0 }} |
        Activos desactivados: {{ $rollbackSummary['activos_desactivados'] ?? 0 }} |
        Documentos conservados como no vigentes: {{ $rollbackSummary['documentos_inhabilitados'] ?? 0 }}
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
            <span class="pill ok">CSV + ZIP documental</span>
        </div>

        <div class="rm-box">
            <h3>Previsualizar expedientes antes de aplicar</h3>
            <p>
                Carga un CSV y su ZIP documental. SWAFI validará la estructura, los catálogos,
                las reglas de negocio y los documentos sin modificar todavía activos o expedientes.
            </p>

            <form method="POST" action="{{ route('registro-masivo.importar') }}" enctype="multipart/form-data">
                @csrf

                <label>
                    <span>Archivo CSV de datos</span>
                    <input type="file" name="archivo_csv" accept=".csv,.txt" required>
                </label>

                <label style="margin-top:12px">
                    <span>Archivo ZIP con documentos PDF/XML</span>
                    <input type="file" name="archivo_zip" accept=".zip" required>
                </label>

                <div class="rm-help">
                    El CSV debe incluir las columnas <strong>Documento PDF</strong> y <strong>Documento XML</strong>.
                    Los nombres capturados ahí deben existir dentro del ZIP. Ejemplo:
                    <strong>factura_184.pdf</strong> y <strong>factura_184.xml</strong>.
                    Si ambos archivos existen, el expediente quedará como completo.
                    La carga solo se confirmará después de revisar la tabla de previsualización.
                </div>

                <div class="action-group" style="margin-top:12px">
                    <button class="tab" type="submit">Previsualizar y validar</button>
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
                <span>Datos del activo</span>
            </div>

            <div class="rm-kpi">
                <strong>ZIP</strong>
                <span>PDF/XML físicos</span>
            </div>

            <div class="rm-kpi">
                <strong>HASH</strong>
                <span>Integridad documental</span>
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
                <strong>Vinculación documental</strong>
                <span>El sistema busca en el ZIP los archivos indicados en las columnas Documento PDF y Documento XML.</span>
            </div>

            <div class="list-item">
                <strong>Estatus documental</strong>
                <span>El expediente queda completo solo si el PDF y el XML fueron encontrados, guardados y ligados al expediente.</span>
            </div>

            <div class="list-item">
                <strong>Integridad</strong>
                <span>Por cada documento físico se registra ruta privada, tamaño, MIME y hash SHA-256.</span>
            </div>

            <div class="list-item">
                <strong>Errores controlados</strong>
                <span>Las filas con catálogos inexistentes, fechas inválidas, montos inválidos, UUID duplicado o documentos faltantes se rechazan.</span>
            </div>

            <div class="list-item">
                <strong>Aplicación confirmada</strong>
                <span>La previsualización no modifica datos. Solo las filas aceptadas se aplican después de una confirmación expresa.</span>
            </div>

            <div class="list-item">
                <strong>Reintento sin duplicidad</strong>
                <span>Las filas corregidas pueden cargarse en un lote nuevo; SWAFI distingue entre inserción y actualización.</span>
            </div>
        </div>
    </div>
</section>

@if ($lote)
<section class="card table-card" style="margin-top:20px">
    <div class="section-title">
        <div>
            <h2>Previsualización del lote</h2>
            <small>{{ $lote->csv_nombre_original }} · {{ $lote->uuid }}</small>
            @if ($canRollbackImports && $lote->usuario)
                <small>Responsable del lote: {{ $lote->usuario->name }} · {{ $lote->usuario->email }}</small>
            @endif
        </div>
        @php
            $batchPill = match ($lote->estado) {
                'aplicada' => 'ok',
                'cancelada', 'revertida' => 'danger',
                default => 'warn',
            };
        @endphp
        <span class="pill {{ $batchPill }}">{{ ucfirst($lote->estado) }}</span>
    </div>

    <div class="rm-preview-summary">
        <div class="rm-preview-stat">
            <strong>{{ $lote->total_filas }}</strong>
            <span>Filas evaluadas</span>
        </div>
        <div class="rm-preview-stat accepted">
            <strong>{{ $lote->filas_aceptadas }}</strong>
            <span>Aceptadas para aplicar</span>
        </div>
        <div class="rm-preview-stat observed">
            <strong>{{ $lote->filas_observadas }}</strong>
            <span>Observadas para corregir</span>
        </div>
        <div class="rm-preview-stat rejected">
            <strong>{{ $lote->filas_rechazadas }}</strong>
            <span>Rechazadas</span>
        </div>
    </div>

    <div class="rm-preview-toolbar">
        <form method="GET" action="{{ route('registro-masivo') }}">
            <input type="hidden" name="lote" value="{{ $lote->uuid }}">
            <label>
                <span>Filtrar previsualización</span>
                <select name="preview_status" onchange="this.form.submit()">
                    <option value="">Todas las filas</option>
                    <option value="aceptada" {{ $previewStatus === 'aceptada' ? 'selected' : '' }}>Aceptadas</option>
                    <option value="observada" {{ $previewStatus === 'observada' ? 'selected' : '' }}>Observadas</option>
                    <option value="rechazada" {{ $previewStatus === 'rechazada' ? 'selected' : '' }}>Rechazadas</option>
                </select>
            </label>
        </form>

        <div class="rm-preview-actions">
            @if (($lote->filas_observadas + $lote->filas_rechazadas) > 0)
                <a class="tab" href="{{ route('registro-masivo.incidencias', $lote->uuid) }}">
                    Descargar incidencias Excel
                </a>
                <a class="tab" href="{{ route('registro-masivo.incidencias-csv', $lote->uuid) }}">
                    Descargar respaldo CSV
                </a>
            @endif

            @if ($lote->estaVigente() && (int) $lote->user_id === (int) auth()->id())
                <form method="POST" action="{{ route('registro-masivo.aplicar', $lote->uuid) }}">
                    @csrf
                    <label class="rm-confirm-box">
                        <input type="checkbox" name="confirmar_aplicacion" value="1" required>
                        Revisé el lote y confirmo aplicar solo las filas aceptadas.
                    </label>
                    <button class="tab" type="submit" style="margin-top:8px">Aplicar carga</button>
                </form>

                <form method="POST" action="{{ route('registro-masivo.cancelar', $lote->uuid) }}" onsubmit="return confirm('¿Cancelar esta previsualización sin aplicar cambios?');">
                    @csrf
                    @method('DELETE')
                    <button class="tab" type="submit">Cancelar lote</button>
                </form>
            @endif
        </div>
    </div>

    @if ($canRollbackImports && $lote->estado === 'aplicada')
        <div class="rm-rollback-panel">
            <h3>HU-029 · Reversión administrativa controlada</h3>
            <p>
                Esta operación restaura los datos anteriores del activo, expediente y valores conciliados,
                conserva los documentos importados como versiones no vigentes y registra la decisión en bitácora.
                La reversión se cancela completa si SWAFI detecta cambios o dependencias posteriores.
            </p>

            @if ($lote->esRevertible())
                <p>
                    Disponible hasta:
                    <strong>{{ $lote->reversion_disponible_hasta?->format('d/m/Y H:i') }}</strong>.
                    Solo el Administrador SWAFI puede ejecutar esta acción.
                </p>

                <form
                    method="POST"
                    action="{{ route('registro-masivo.revertir', $lote->uuid) }}"
                    onsubmit="return confirm('¿Confirmas la reversión completa de este lote? SWAFI volverá a validar dependencias antes de modificar información.');"
                >
                    @csrf
                    @method('PATCH')

                    <label>
                        <span>Motivo administrativo de la reversión</span>
                        <textarea
                            name="motivo_reversion"
                            minlength="20"
                            maxlength="500"
                            required
                            placeholder="Describe la causa, autorización y alcance esperado de la reversión."
                        >{{ old('motivo_reversion') }}</textarea>
                    </label>

                    <label class="rm-confirm-box" style="margin-top:10px">
                        <input type="checkbox" name="confirmar_reversion" value="1" required>
                        Confirmo que revisé el lote y que la reversión debe ejecutarse de forma integral.
                    </label>

                    <button class="tab" type="submit" style="margin-top:10px">
                        Revertir lote aplicado
                    </button>
                </form>
            @else
                <div class="rm-rollback-warning">
                    {{ $lote->motivoNoRevertible() ?? 'El lote no puede revertirse en su estado actual.' }}
                </div>
            @endif
        </div>
    @elseif ($lote->estado === 'revertida')
        <div class="rm-rollback-panel is-complete">
            <h3>Lote revertido con trazabilidad</h3>
            <p>
                Fecha: <strong>{{ $lote->revertida_at?->format('d/m/Y H:i') }}</strong><br>
                Motivo: {{ $lote->motivo_reversion ?: 'Sin motivo disponible.' }}
            </p>
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Fila</th>
                <th>Activo / factura</th>
                <th>Datos principales</th>
                <th>Acción</th>
                <th>Clasificación</th>
                <th>Resultado de validación</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filasPreview as $fila)
                @php
                    $data = $fila->datos ?? [];
                    $statusPill = $fila->estatus === 'aceptada' ? 'ok' : ($fila->estatus === 'observada' ? 'warn' : 'danger');
                @endphp
                <tr>
                    <td><strong>{{ $fila->numero_fila }}</strong></td>
                    <td>
                        <strong>{{ $data['numero_activo'] ?? 'Sin activo' }}</strong><br>
                        <small>{{ $data['folio_factura'] ?? 'Sin folio' }}</small>
                    </td>
                    <td>
                        {{ $data['descripcion'] ?? '' }}<br>
                        <small>{{ $data['planta_clave'] ?? '' }} · {{ $data['proveedor_rfc'] ?? '' }} · {{ $data['moneda'] ?? '' }} {{ $data['monto_factura'] ?? '' }}</small>
                    </td>
                    <td>{{ $fila->accion ? ucfirst($fila->accion) : 'No aplicable' }}</td>
                    <td><span class="pill {{ $statusPill }}">{{ ucfirst($fila->estatus) }}</span></td>
                    <td>
                        @if (!empty($fila->errores))
                            <ul class="rm-issue-list error">
                                @foreach ($fila->errores as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if (!empty($fila->advertencias))
                            <ul class="rm-issue-list warning">
                                @foreach ($fila->advertencias as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if (empty($fila->errores) && empty($fila->advertencias))
                            <span class="pill ok">Sin incidencias</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">No hay filas para el filtro seleccionado.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="table-footer">
        <div class="table-summary">
            Mostrando {{ $filasPreview->firstItem() ?? 0 }}–{{ $filasPreview->lastItem() ?? 0 }}
            de {{ $filasPreview->total() }} filas
        </div>
        <div class="table-pagination">
            @if ($filasPreview->onFirstPage())
                <span class="page-link disabled">Anterior</span>
            @else
                <a class="page-link" href="{{ $filasPreview->previousPageUrl() }}">Anterior</a>
            @endif
            <span class="page-link active">{{ $filasPreview->currentPage() }}</span>
            @if ($filasPreview->hasMorePages())
                <a class="page-link" href="{{ $filasPreview->nextPageUrl() }}">Siguiente</a>
            @else
                <span class="page-link disabled">Siguiente</span>
            @endif
        </div>
        <div class="table-page-size"><span>HU-019 a HU-026</span></div>
    </div>
</section>
@endif

@if ($lotesRecientes->isNotEmpty())
<section class="card" style="margin-top:20px">
    <div class="section-title">
        <h2>Historial reciente de importaciones</h2>
        <span class="pill ok">Trazabilidad por lote</span>
    </div>
    <div class="rm-batches">
        @foreach ($lotesRecientes as $batch)
            <a class="rm-batch-item" href="{{ route('registro-masivo', ['lote' => $batch->uuid]) }}">
                <strong>{{ \Illuminate\Support\Str::limit($batch->csv_nombre_original, 28) }}</strong>
                <span>{{ $batch->created_at?->format('d/m/Y H:i') }} · {{ ucfirst($batch->estado) }}</span>
                @if ($canRollbackImports && $batch->usuario)
                    <span>{{ $batch->usuario->name }} · {{ $batch->usuario->email }}</span>
                @endif
                <span>{{ $batch->filas_aceptadas }} aceptadas · {{ $batch->filas_rechazadas }} rechazadas</span>
                @if ($batch->estado === 'aplicada' && $batch->reversion_disponible_hasta)
                    <span>Reversión hasta {{ $batch->reversion_disponible_hasta->format('d/m/Y H:i') }}</span>
                @endif
            </a>
        @endforeach
    </div>
</section>
@endif

<div data-swafi-query-workspace data-swafi-query-key="registro-masivo">
<section class="card" style="margin-top:20px" data-swafi-query-panel>
    <div class="section-title">
        <h2>Filtros de consulta</h2>
        <span class="pill ok">Paginación real</span>
    </div>

    <form method="GET" action="{{ route('registro-masivo') }}" class="rm-filter" data-swafi-query-form>
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
                    @foreach (($catalogos['estatusDocumentales'] ?? collect()) as $estatusDocumental)
                        <option value="{{ $estatusDocumental->clave }}" {{ $estatusSeleccionado === $estatusDocumental->clave ? 'selected' : '' }}>
                            {{ $estatusDocumental->nombre }}
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
                <span>Registros por página</span>
                <select name="per_page">
                    @foreach ([10, 25, 50, 100] as $size)
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

<section class="card table-card" style="margin-top:20px" data-swafi-query-results id="swafi-registro-masivo-resultados">
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
                            {{ $documentaryStatusLabels[$estatusFila] ?? \Illuminate\Support\Str::headline($estatusFila) }}
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
</div>

@endsection
