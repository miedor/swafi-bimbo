@extends('layouts.app')

@section('title', 'Catálogos base | SWAFI')
@section('page_title', 'Catálogos base')
@section('page_subtitle', 'Consulta y administración controlada de catálogos transversales del sistema')
@section('breadcrumb', 'Catálogos base')

@section('page_styles')
<style nonce="{{ request()->attributes->get('csp_nonce') }}">
    .cat-grid {
        display: grid;
        grid-template-columns: 0.9fr 1.1fr;
        gap: 18px;
        align-items: start;
    }

    .cat-grid-readonly {
        grid-template-columns: 1fr;
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

    .cat-detail-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-top: 14px;
    }

    .cat-detail-item {
        min-width: 0;
        padding: 12px;
        border: 1px solid #e1eaf6;
        border-radius: 14px;
        background: #f8fbff;
    }

    .cat-detail-item span {
        display: block;
        margin-bottom: 5px;
        color: #64748b;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .cat-detail-item strong {
        display: block;
        overflow-wrap: anywhere;
        color: #12345a;
        font-size: 13px;
    }

    .cat-dependency-list {
        margin: 10px 0 0 18px;
        color: #7a3e00;
        font-size: 13px;
    }

    .cat-readonly-note {
        margin-top: 14px;
        padding: 12px 14px;
        border: 1px solid #cfe0f5;
        border-radius: 14px;
        background: #eef6ff;
        color: #264b73;
        font-size: 13px;
        line-height: 1.45;
    }

    .cat-import-preview {
        margin-top: 20px;
        scroll-margin-top: 105px;
    }

    .cat-preview-meta {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-top: 12px;
    }

    .cat-preview-meta-item {
        min-width: 0;
        padding: 11px 12px;
        border: 1px solid #e1eaf6;
        border-radius: 14px;
        background: #f8fbff;
    }

    .cat-preview-meta-item span {
        display: block;
        margin-bottom: 4px;
        color: #64748b;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .cat-preview-meta-item strong {
        display: block;
        overflow-wrap: anywhere;
        color: #12345a;
        font-size: 13px;
    }

    .cat-import-kpi-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 10px;
        margin-top: 14px;
    }

    .cat-import-kpi-grid .cat-kpi.is-accepted {
        background: #eefaf1;
        border-color: #c7ead0;
    }

    .cat-import-kpi-grid .cat-kpi.is-observed {
        background: #fff8e8;
        border-color: #f0d799;
    }

    .cat-import-kpi-grid .cat-kpi.is-rejected {
        background: #fff0f0;
        border-color: #efc0c0;
    }

    .cat-import-filter {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) auto;
        gap: 10px;
        align-items: end;
        margin-top: 14px;
        padding: 12px;
        border: 1px solid #e1eaf6;
        border-radius: 15px;
        background: #f8fbff;
    }

    .cat-import-filter label span {
        display: block;
        margin-bottom: 5px;
        color: #1d3558;
        font-size: 12px;
        font-weight: 900;
    }

    .cat-import-filter select {
        width: 100%;
        min-height: 38px;
        padding: 8px 10px;
        border: 1px solid #d5e1ef;
        border-radius: 11px;
        background: #ffffff;
        color: #16304d;
    }

    .cat-preview-messages {
        margin: 0;
        padding-left: 18px;
        color: #5b6780;
        font-size: 12px;
        line-height: 1.35;
    }

    .cat-preview-messages.is-error {
        color: #9b2c2c;
    }

    .cat-import-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        margin-top: 15px;
    }

    .cat-import-confirm {
        flex: 1 1 420px;
        padding: 12px;
        border: 1px solid #cfe0f5;
        border-radius: 14px;
        background: #eef6ff;
    }

    .cat-import-confirm label {
        display: flex;
        gap: 9px;
        align-items: flex-start;
        color: #264b73;
        font-size: 13px;
        font-weight: 800;
        line-height: 1.4;
    }

    .cat-import-confirm input[type="checkbox"] {
        width: 18px;
        height: 18px;
        margin-top: 1px;
        flex: 0 0 auto;
    }

    .cat-import-state-note {
        margin-top: 14px;
        padding: 11px 13px;
        border-radius: 13px;
        background: #f8fbff;
        color: #52657c;
        font-size: 13px;
        line-height: 1.45;
    }

    @media (max-width: 1100px) {
        .cat-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 760px) {
        .cat-form-grid,
        .query-grid-four,
        .cat-kpi-grid,
        .cat-detail-grid,
        .cat-preview-meta,
        .cat-import-kpi-grid,
        .cat-import-filter {
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

@if (session('import_apply_summary'))
    @php
        $summary = session('import_apply_summary');
    @endphp

    <div class="cat-message cat-message-success">
        <strong>La carga de catálogos se aplicó de forma transaccional.</strong><br>
        Aplicados: {{ $summary['aplicados'] ?? 0 }} |
        Insertados: {{ $summary['insertados'] ?? 0 }} |
        Actualizados: {{ $summary['actualizados'] ?? 0 }} |
        Filas rechazadas no aplicadas: {{ $summary['rechazados'] ?? 0 }}
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

<section class="cat-grid {{ $canAdminCatalogs ? '' : 'cat-grid-readonly' }}">
    @if ($canAdminCatalogs)
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
                    <select data-navigate-base="{{ route('catalogos') }}?catalogo=">
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

                    <label class="cat-field-wide">
                        <span>Dirección</span>
                        <input name="direccion" value="{{ old('direccion', $registroEdit->direccion ?? '') }}" maxlength="255" required>
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
                        <span>Planta responsable</span>
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
                        <span>Clave</span>
                        <input name="clave" value="{{ old('clave', $registroEdit->clave ?? '') }}" required>
                    </label>

                    <label class="cat-field-wide">
                        <span>Descripción</span>
                        <input name="descripcion" value="{{ old('descripcion', $registroEdit->descripcion ?? '') }}" required>
                    </label>
                @endif

                @if ($catalogoActivo === 'categorias_activo')
                    <label>
                        <span>Clave de categoría</span>
                        <input name="clave" value="{{ old('clave', $registroEdit->clave ?? '') }}" maxlength="30" required>
                    </label>

                    <label>
                        <span>Nombre de categoría</span>
                        <input name="nombre" value="{{ old('nombre', $registroEdit->nombre ?? '') }}" maxlength="120" required>
                    </label>

                    <label class="cat-field-wide">
                        <span>Descripción</span>
                        <input name="descripcion" value="{{ old('descripcion', $registroEdit->descripcion ?? '') }}" maxlength="255">
                    </label>
                @endif

                @if ($catalogoActivo === 'tipos_activo')
                    <label>
                        <span>Categoría</span>
                        <select name="categoria_activo_id" required>
                            <option value="">Seleccione...</option>
                            @foreach ($opciones['categorias_activo'] as $categoria)
                                @php
                                    $categoriaSeleccionada = (string) old('categoria_activo_id', $registroEdit->categoria_activo_id ?? '');
                                @endphp
                                <option value="{{ $categoria->id }}" {{ $categoriaSeleccionada === (string) $categoria->id ? 'selected' : '' }}>
                                    {{ $categoria->clave }} - {{ $categoria->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    @if ($opciones['categorias_activo']->isEmpty())
                        <div class="cat-message cat-message-error cat-field-wide" style="margin:0">
                            Registra y activa primero una categoría de activo antes de crear tipos nuevos.
                            <a href="{{ route('catalogos', ['catalogo' => 'categorias_activo']) }}">Ir a categorías de activo</a>.
                        </div>
                    @endif

                    <label>
                        <span>Clave del tipo</span>
                        <input name="clave" value="{{ old('clave', $registroEdit->clave ?? '') }}" maxlength="30" required>
                    </label>

                    <label class="cat-field-wide">
                        <span>Nombre del tipo de activo</span>
                        <input name="descripcion" value="{{ old('descripcion', $registroEdit->descripcion ?? '') }}" maxlength="120" required>
                    </label>

                    <label>
                        <span>Vida útil meses</span>
                        <input type="number" name="vida_util_meses" min="1" max="600" value="{{ old('vida_util_meses', $registroEdit->vida_util_meses ?? '') }}">
                    </label>
                @endif

                @if (in_array($catalogoActivo, ['estatus_documentales', 'estatus_operativos'], true))
                    <label>
                        <span>Clave técnica</span>
                        <input
                            name="clave"
                            value="{{ old('clave', $registroEdit->clave ?? '') }}"
                            maxlength="20"
                            pattern="[a-z][a-z0-9_]*"
                            {{ $editing ? 'readonly' : '' }}
                            required
                        >
                    </label>

                    <label>
                        <span>Nombre visible</span>
                        <input name="nombre" value="{{ old('nombre', $registroEdit->nombre ?? '') }}" maxlength="80" required>
                    </label>

                    <label class="cat-field-wide">
                        <span>Descripción</span>
                        <input name="descripcion" value="{{ old('descripcion', $registroEdit->descripcion ?? '') }}" maxlength="255">
                    </label>

                    <label>
                        <span>Orden de presentación</span>
                        <input type="number" name="orden" min="1" max="999" value="{{ old('orden', $registroEdit->orden ?? 100) }}" required>
                    </label>

                    <div class="cat-help cat-field-wide" style="margin-top:0">
                        La clave técnica se utiliza en reglas, filtros e integridad referencial. Después de crear el estatus
                        no puede modificarse. Los estatus base de SWAFI pueden actualizar su nombre y descripción, pero no
                        pueden desactivarse.
                    </div>
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
                        <span>Clave del área</span>
                        <input name="clave" value="{{ old('clave', $registroEdit->clave ?? '') }}" required>
                    </label>

                    <label class="cat-field-wide">
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
                                    {{ $area->planta_nombre }} / {{ $area->clave }} - {{ $area->nombre }}
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
            <h3>Carga masiva con previsualización</h3>
            <p>
                SWAFI valida el layout y clasifica cada fila como aceptada, observada o rechazada.
                Ningún catálogo se modifica hasta que revises el resultado y confirmes expresamente la aplicación del lote.
            </p>

            <form method="POST" action="{{ route('catalogos.importar') }}" enctype="multipart/form-data">
                @csrf

                <input type="hidden" name="catalogo" value="{{ $catalogoActivo }}">

                <label>
                    <span>Layout CSV o XLSX</span>
                    <input type="file" name="archivo_csv" accept=".csv,.txt,.xlsx" required>
                </label>

                <div class="cat-help">
                    Encabezados esperados para este catálogo:
                    <strong>{{ implode(', ', $headersLayout) }}</strong>.
                    El archivo no debe contener fórmulas. Para CSV utiliza codificación UTF-8.
                </div>

                <div class="action-group" style="margin-top:12px">
                    <button class="tab" type="submit">Previsualizar y validar</button>
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
    @endif

    <div class="card">
        <div class="section-title">
            <h2>Resumen del catálogo</h2>
            <span class="pill ok">Conectado a MySQL</span>
        </div>

        <label class="cat-filter" style="display:block;margin-bottom:14px">
            <span>Catálogo a consultar</span>
            <select data-navigate-base="{{ route('catalogos') }}?catalogo=">
                @foreach ($catalogosDisponibles as $key => $label)
                    <option value="{{ $key }}" {{ $catalogoActivo === $key ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </label>

        @unless ($canAdminCatalogs)
            <div class="cat-readonly-note">
                Tu perfil cuenta con acceso de consulta. Las altas, modificaciones, importaciones, activaciones y
                desactivaciones requieren el permiso <strong>catalogos.administrar</strong>.
            </div>
        @endunless

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
                <strong>{{ $canAdminCatalogs ? 'CRUD' : 'Consulta' }}</strong>
                <span>{{ $canAdminCatalogs ? 'Administración y CSV' : 'Acceso de solo lectura' }}</span>
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

@if ($canAdminCatalogs && $importBatch !== null)
    @php
        $importSummary = is_array($importBatch->resumen) ? $importBatch->resumen : [];
        $importCanApply = $importBatch->puedeAplicarse();
        $importIncidentCount = (int) $importBatch->filas_observadas + (int) $importBatch->filas_rechazadas;
        $importStateClass = match ($importBatch->estado) {
            'aplicada' => 'ok',
            'cancelada', 'expirada' => 'danger',
            default => 'warn',
        };
        $importStatusFilter = (string) ($filtros['import_status'] ?? '');
    @endphp

    <section class="card table-card cat-import-preview" id="swafi-catalog-import-preview">
        <div class="section-title">
            <h2>Previsualización del layout</h2>
            <span class="pill {{ $importStateClass }}">{{ ucfirst($importBatch->estado) }}</span>
        </div>

        <div class="cat-preview-meta">
            <div class="cat-preview-meta-item">
                <span>Catálogo</span>
                <strong>{{ $catalogosDisponibles[$importBatch->catalogo] ?? $importBatch->catalogo }}</strong>
            </div>
            <div class="cat-preview-meta-item">
                <span>Archivo</span>
                <strong>{{ $importBatch->archivo_nombre_original }}</strong>
            </div>
            <div class="cat-preview-meta-item">
                <span>Huella SHA-256</span>
                <strong>{{ $importBatch->archivo_hash_sha256 }}</strong>
            </div>
            <div class="cat-preview-meta-item">
                <span>Vigencia</span>
                <strong>{{ $importBatch->expira_at?->format('d/m/Y H:i') ?? 'Sin vencimiento' }}</strong>
            </div>
        </div>

        <div class="cat-import-kpi-grid">
            <div class="cat-kpi">
                <strong>{{ number_format((int) $importBatch->total_filas) }}</strong>
                <span>Filas evaluadas</span>
            </div>
            <div class="cat-kpi is-accepted">
                <strong>{{ number_format((int) $importBatch->filas_aceptadas) }}</strong>
                <span>Aceptadas</span>
            </div>
            <div class="cat-kpi is-observed">
                <strong>{{ number_format((int) $importBatch->filas_observadas) }}</strong>
                <span>Observadas</span>
            </div>
            <div class="cat-kpi is-rejected">
                <strong>{{ number_format((int) $importBatch->filas_rechazadas) }}</strong>
                <span>Rechazadas</span>
            </div>
            <div class="cat-kpi">
                <strong>{{ number_format((int) ($importSummary['insertar'] ?? 0)) }}</strong>
                <span>Altas propuestas</span>
            </div>
            <div class="cat-kpi">
                <strong>{{ number_format((int) ($importSummary['actualizar'] ?? 0)) }}</strong>
                <span>Actualizaciones propuestas</span>
            </div>
        </div>

        <form method="GET" action="{{ route('catalogos') }}" class="cat-import-filter">
            <input type="hidden" name="catalogo" value="{{ $catalogoActivo }}">
            <input type="hidden" name="lote" value="{{ $importBatch->uuid }}">

            <label>
                <span>Filtrar clasificación</span>
                <select name="import_status">
                    <option value="">Todas las filas</option>
                    <option value="aceptada" {{ $importStatusFilter === 'aceptada' ? 'selected' : '' }}>Aceptadas</option>
                    <option value="observada" {{ $importStatusFilter === 'observada' ? 'selected' : '' }}>Observadas</option>
                    <option value="rechazada" {{ $importStatusFilter === 'rechazada' ? 'selected' : '' }}>Rechazadas</option>
                </select>
            </label>

            <div class="action-group">
                <button class="tab" type="submit">Filtrar previsualización</button>
                <a class="tab" href="{{ route('catalogos', ['catalogo' => $catalogoActivo, 'lote' => $importBatch->uuid]) }}">Limpiar filtro</a>
            </div>
        </form>

        <div class="cat-table-scroll" style="margin-top:14px">
            <table>
                <thead>
                    <tr>
                        <th>Fila</th>
                        <th>Identificador</th>
                        <th>Acción propuesta</th>
                        <th>Clasificación</th>
                        <th>Resultado de validación</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($importRows as $importRow)
                        @php
                            $rowData = is_array($importRow->datos) ? $importRow->datos : [];
                            $rowErrors = is_array($importRow->errores) ? $importRow->errores : [];
                            $rowWarnings = is_array($importRow->advertencias) ? $importRow->advertencias : [];
                            $rowIdentifier = $rowData['rfc']
                                ?? $rowData['clave']
                                ?? $rowData['codigo_interno']
                                ?? $rowData['correo']
                                ?? $rowData['nombre']
                                ?? '—';
                            $rowPillClass = match ($importRow->estatus) {
                                'aceptada' => 'ok',
                                'rechazada' => 'danger',
                                default => 'warn',
                            };
                        @endphp
                        <tr>
                            <td>{{ $importRow->numero_fila }}</td>
                            <td><strong>{{ $rowIdentifier }}</strong></td>
                            <td>{{ $importRow->accion ? ucfirst($importRow->accion) : 'No aplicable' }}</td>
                            <td><span class="pill {{ $rowPillClass }}">{{ ucfirst($importRow->estatus) }}</span></td>
                            <td>
                                @if ($rowErrors !== [])
                                    <ul class="cat-preview-messages is-error">
                                        @foreach ($rowErrors as $message)
                                            <li>{{ $message }}</li>
                                        @endforeach
                                    </ul>
                                @elseif ($rowWarnings !== [])
                                    <ul class="cat-preview-messages">
                                        @foreach ($rowWarnings as $message)
                                            <li>{{ $message }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="pill ok">Sin incidencias</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No existen filas con la clasificación seleccionada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($importRows !== null)
            <div class="table-footer">
                <div class="table-summary">
                    Mostrando {{ $importRows->firstItem() ?? 0 }}–{{ $importRows->lastItem() ?? 0 }}
                    de {{ $importRows->total() }} filas
                </div>
                <div class="table-pagination">
                    @if ($importRows->onFirstPage())
                        <span class="page-link disabled">Anterior</span>
                    @else
                        <a class="page-link" href="{{ $importRows->previousPageUrl() }}">Anterior</a>
                    @endif
                    <span class="page-link active">{{ $importRows->currentPage() }}</span>
                    @if ($importRows->hasMorePages())
                        <a class="page-link" href="{{ $importRows->nextPageUrl() }}">Siguiente</a>
                    @else
                        <span class="page-link disabled">Siguiente</span>
                    @endif
                </div>
                <div class="table-page-size"><span>HU-103 · validación previa</span></div>
            </div>
        @endif

        <div class="cat-import-actions">
            @if ($importCanApply)
                <form
                    method="POST"
                    action="{{ route('catalogos.importaciones.aplicar', ['lote' => $importBatch->uuid]) }}"
                    class="cat-import-confirm"
                    data-confirm="Se aplicarán únicamente las filas aceptadas y observadas. Las rechazadas permanecerán sin cambios. ¿Deseas continuar?"
                >
                    @csrf
                    <input type="hidden" name="catalogo" value="{{ $importBatch->catalogo }}">
                    <label>
                        <input type="checkbox" name="confirmar_aplicacion" value="1" required>
                        <span>Revisé el lote y confirmo aplicar las filas aceptadas y observadas. Las filas rechazadas no modificarán los catálogos.</span>
                    </label>
                    <button class="tab" type="submit" style="margin-top:10px">Aplicar carga validada</button>
                </form>
            @else
                <div class="cat-import-state-note">
                    Este lote no admite aplicación porque ya fue aplicado, cancelado, venció o no contiene filas válidas.
                </div>
            @endif

            @if ($importBatch->estado === 'previsualizada')
                <form method="POST" action="{{ route('catalogos.importaciones.cancelar', ['lote' => $importBatch->uuid]) }}">
                    @csrf
                    @method('DELETE')
                    <button class="tab" type="submit" data-confirm="¿Deseas cancelar esta previsualización sin modificar los catálogos?">
                        Cancelar lote
                    </button>
                </form>
            @endif

            @if ($importIncidentCount > 0)
                <a class="tab" href="{{ route('catalogos.importaciones.incidencias.xlsx', ['lote' => $importBatch->uuid]) }}">
                    Incidencias Excel
                </a>
                <a class="tab" href="{{ route('catalogos.importaciones.incidencias.csv', ['lote' => $importBatch->uuid]) }}">
                    Incidencias CSV
                </a>
            @endif

            <a class="tab" href="{{ route('catalogos', ['catalogo' => $catalogoActivo]) }}">Cerrar previsualización</a>
        </div>
    </section>
@endif

@if ($registroDetail !== null)
<section class="card" style="margin-top:20px" id="swafi-catalogo-detalle">
    <div class="section-title">
        <h2>Detalle de {{ $catalogosDisponibles[$catalogoActivo] ?? 'catálogo' }}</h2>
        <a class="tab" href="{{ route('catalogos', array_merge(request()->except(['detalle', 'editar']), ['catalogo' => $catalogoActivo])) }}">
            Cerrar detalle
        </a>
    </div>

    <div class="cat-detail-grid">
        @foreach ($columnas as $key => $label)
            <div class="cat-detail-item">
                <span>{{ $label }}</span>
                <strong>{{ data_get($registroDetail, $key) !== null && data_get($registroDetail, $key) !== '' ? data_get($registroDetail, $key) : '—' }}</strong>
            </div>
        @endforeach

        <div class="cat-detail-item">
            <span>Creado</span>
            <strong>{{ $registroDetail->created_at ?? '—' }}</strong>
        </div>

        <div class="cat-detail-item">
            <span>Última actualización</span>
            <strong>{{ $registroDetail->updated_at ?? '—' }}</strong>
        </div>
    </div>

    @if (in_array($catalogoActivo, ['plantas', 'centros_costo', 'categorias_activo', 'tipos_activo', 'estatus_documentales', 'estatus_operativos', 'areas'], true))
        @if ($dependenciasCatalogo === [])
            <div class="cat-message cat-message-success" style="margin-top:14px;margin-bottom:0">
                @if ($catalogoActivo === 'plantas')
                    Esta planta no presenta dependencias activas o históricas que impidan su desactivación.
                @elseif ($catalogoActivo === 'centros_costo')
                    Este centro de costo no presenta activos vigentes que impidan su desactivación.
                @elseif ($catalogoActivo === 'categorias_activo')
                    Esta categoría no presenta tipos de activo activos que impidan su desactivación.
                @elseif ($catalogoActivo === 'tipos_activo')
                    Este tipo de activo no presenta activos vigentes que impidan su desactivación.
                @elseif ($catalogoActivo === 'estatus_documentales')
                    Este estatus documental personalizado no está en uso y puede desactivarse.
                @elseif ($catalogoActivo === 'estatus_operativos')
                    Este estatus operativo personalizado no está en uso y puede desactivarse.
                @else
                    Esta área no presenta ubicaciones, activos o traslados pendientes que impidan su desactivación.
                @endif
            </div>
        @else
            <div class="cat-message cat-message-error" style="margin-top:14px;margin-bottom:0">
                <strong>
                    @if ($catalogoActivo === 'plantas')
                        Dependencias que protegen la integridad de la planta:
                    @elseif ($catalogoActivo === 'centros_costo')
                        Dependencias que protegen la integridad del centro de costo:
                    @elseif ($catalogoActivo === 'categorias_activo')
                        Dependencias que protegen la integridad de la categoría de activo:
                    @elseif ($catalogoActivo === 'tipos_activo')
                        Dependencias que protegen la integridad del tipo de activo:
                    @elseif ($catalogoActivo === 'estatus_documentales')
                        Dependencias que protegen la integridad del estatus documental:
                    @elseif ($catalogoActivo === 'estatus_operativos')
                        Dependencias que protegen la integridad del estatus operativo:
                    @else
                        Dependencias que protegen la integridad del área:
                    @endif
                </strong>
                <ul class="cat-dependency-list">
                    @foreach ($dependenciasCatalogo as $descripcion => $cantidad)
                        <li>{{ $cantidad }} {{ $descripcion }}</li>
                    @endforeach
                </ul>
                El registro no podrá desactivarse hasta regularizar estas relaciones.
            </div>
        @endif
    @endif
</section>
@endif

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

            @if (in_array($catalogoActivo, ['centros_costo', 'areas', 'ubicaciones'], true))
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
            @endif

            @if ($catalogoActivo === 'tipos_activo')
                <label>
                    <span>Categoría</span>
                    <select name="categoria_activo_id">
                        <option value="">Todas</option>
                        @foreach ($opciones['categorias_activo'] as $categoria)
                            @php
                                $filtroCategoria = (string) ($filtros['categoria_activo_id'] ?? '');
                            @endphp
                            <option value="{{ $categoria->id }}" {{ $filtroCategoria === (string) $categoria->id ? 'selected' : '' }}>
                                {{ $categoria->clave }} - {{ $categoria->nombre }}
                            </option>
                        @endforeach
                    </select>
                </label>
            @endif

            @if ($catalogoActivo === 'ubicaciones')
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
            @endif
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
                    @if ($canAdminCatalogs)
                        <button class="tab" type="submit" name="export" value="csv">Exportar CSV</button>
                    @endif
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
        <span class="pill ok">{{ $canAdminCatalogs ? 'CRUD + carga masiva' : 'Consulta autorizada' }}</span>
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
                                @elseif ($key === 'es_sistema')
                                    <span class="pill {{ ($row->es_sistema ?? false) ? 'ok' : 'warn' }}">
                                        {{ ($row->es_sistema ?? false) ? 'Sí' : 'No' }}
                                    </span>
                                @else
                                    {{ data_get($row, $key) !== null && data_get($row, $key) !== '' ? data_get($row, $key) : '—' }}
                                @endif
                            </td>
                        @endforeach

                        <td>{{ $row->updated_at ?? '—' }}</td>

                        <td>
                            <div class="table-actions">
                                <a href="{{ route('catalogos', array_merge(request()->except(['detalle', 'editar']), ['catalogo' => $catalogoActivo, 'detalle' => $row->id])) }}">
                                    Ver detalle
                                </a>

                                @if ($canAdminCatalogs)
                                    <a href="{{ route('catalogos', array_merge(request()->except(['detalle', 'editar']), ['catalogo' => $catalogoActivo, 'editar' => $row->id])) }}">
                                        Editar
                                    </a>

                                    @if (($row->estatus ?? 'activo') === 'activo')
                                        @if (in_array($catalogoActivo, ['estatus_documentales', 'estatus_operativos'], true) && ($row->es_sistema ?? false))
                                            <span class="pill ok" title="Estatus base requerido por las reglas automáticas de SWAFI">
                                                Protegido
                                            </span>
                                        @else
                                            <form
                                                method="POST"
                                                action="{{ route('catalogos.destroy', [$catalogoActivo, $row->id]) }}"
                                                data-confirm="{{ $catalogoActivo === 'plantas' ? 'SWAFI verificará activos, centros de costo, áreas, ubicaciones, inventarios y traslados. ¿Deseas intentar desactivar esta planta?' : (in_array($catalogoActivo, ['centros_costo', 'categorias_activo', 'tipos_activo', 'estatus_documentales', 'estatus_operativos', 'areas'], true) ? 'SWAFI verificará las dependencias operativas y la protección de estatus base antes de desactivar el registro. ¿Deseas continuar?' : '¿Deseas desactivar este registro del catálogo?') }}"
                                                style="display:inline"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="estatus" value="inactivo">
                                                <button
                                                    type="submit"
                                                    style="border:0;background:none;color:#b42318;font-weight:800;cursor:pointer;padding:0"
                                                >
                                                    Desactivar
                                                </button>
                                            </form>
                                        @endif
                                    @else
                                        <form
                                            method="POST"
                                            action="{{ route('catalogos.activate', [$catalogoActivo, $row->id]) }}"
                                            data-confirm="¿Deseas reactivar este registro del catálogo?"
                                            style="display:inline"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="estatus" value="activo">
                                            <button
                                                type="submit"
                                                style="border:0;background:none;color:#176b35;font-weight:800;cursor:pointer;padding:0"
                                            >
                                                Activar
                                            </button>
                                        </form>
                                    @endif
                                @endif
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
            <span>{{ $canAdminCatalogs ? 'M04 catálogos administrables' : 'M04 consulta de catálogos' }}</span>
        </div>
    </div>
</section>
</div>

@endsection
