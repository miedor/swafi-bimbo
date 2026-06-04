@extends('layouts.app')

@section('title', 'Registro individual | SWAFI')
@section('page_title', 'Registro individual')
@section('page_subtitle', 'Captura manual de expedientes de activo fijo')
@section('breadcrumb', 'Registro individual')
@section('content')

<section class="card form-card swafi-compact-page">
    <div class="compact-page-header">
        <div>
            <h2>Registro individual de expedientes</h2>
            <p>Captura ordenada por secciones para evitar desplazamiento excesivo.</p>
        </div>

        <div class="compact-actions">
            <button type="submit" form="registroIndividualForm" class="btn btn-primary">Guardar</button>
            <a class="btn btn-secondary" href="{{ route('registro-individual') }}">Limpiar</a>
            <a class="btn btn-light" href="{{ route('dashboard') }}">Dashboard</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert-compact alert-success-compact">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert-compact alert-error-compact">
            <strong>Se encontraron errores:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="registroIndividualForm" method="POST" action="{{ route('registro-individual.store') }}" class="compact-tabs-form">
        @csrf

        <input class="compact-tab-radio" type="radio" id="tab-factura" name="registro_tab" checked>
        <input class="compact-tab-radio" type="radio" id="tab-activo" name="registro_tab">
        <input class="compact-tab-radio" type="radio" id="tab-control" name="registro_tab">
        <input class="compact-tab-radio" type="radio" id="tab-documentos" name="registro_tab">

        <div class="compact-tabs-nav" aria-label="Secciones del registro individual">
            <label for="tab-factura">1. Factura</label>
            <label for="tab-activo">2. Activo</label>
            <label for="tab-control">3. Control y ubicación</label>
            <label for="tab-documentos">4. Documentos</label>
        </div>

        <div class="compact-tab-panels">
            <fieldset class="compact-panel panel-factura">
                <legend>Datos de factura</legend>
                <div class="compact-field-grid compact-field-grid-4">
                    <label>
                        <span>Folio de factura</span>
                        <input name="folio_factura" value="{{ old('folio_factura') }}">
                    </label>

                    <label class="span-2">
                        <span>UUID CFDI</span>
                        <input name="uuid_cfdi" value="{{ old('uuid_cfdi') }}">
                    </label>

                    <label>
                        <span>Fecha de factura</span>
                        <input type="date" name="fecha_factura" value="{{ old('fecha_factura') }}">
                    </label>

                    <label>
                        <span>Monto fiscal</span>
                        <input type="number" step="0.01" name="monto_factura" value="{{ old('monto_factura') }}">
                    </label>

                    <label>
                        <span>Moneda</span>
                        <select name="moneda">
                            <option value="MXN" @selected(old('moneda', 'MXN') == 'MXN')>MXN</option>
                            <option value="USD" @selected(old('moneda') == 'USD')>USD</option>
                            <option value="EUR" @selected(old('moneda') == 'EUR')>EUR</option>
                        </select>
                    </label>

                    <label class="span-2">
                        <span>Proveedor</span>
                        <select name="proveedor_id">
                            <option value="">Seleccione...</option>
                            @foreach ($proveedores as $item)
                                <option value="{{ $item->id }}" @selected(old('proveedor_id') == $item->id)>{{ $item->label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </fieldset>

            <fieldset class="compact-panel panel-activo">
                <legend>Datos del activo</legend>
                <div class="compact-field-grid compact-field-grid-4">
                    <label>
                        <span>Número de activo fijo</span>
                        <input name="numero_activo" value="{{ old('numero_activo') }}">
                    </label>

                    <label>
                        <span>Tipo de activo</span>
                        <select name="tipo_activo_id">
                            <option value="">Seleccione...</option>
                            @foreach ($tiposActivo as $item)
                                <option value="{{ $item->id }}" @selected(old('tipo_activo_id') == $item->id)>{{ $item->label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Serie</span>
                        <input name="serie" value="{{ old('serie') }}">
                    </label>

                    <label>
                        <span>Marca</span>
                        <input name="marca" value="{{ old('marca') }}">
                    </label>

                    <label>
                        <span>Modelo</span>
                        <input name="modelo" value="{{ old('modelo') }}">
                    </label>

                    <label>
                        <span>Fecha de adquisición</span>
                        <input type="date" name="fecha_adquisicion" value="{{ old('fecha_adquisicion') }}">
                    </label>

                    <label>
                        <span>Estatus operativo</span>
                        <select name="estatus_operativo">
                            <option value="en_operacion" @selected(old('estatus_operativo') == 'en_operacion')>En operación</option>
                            <option value="baja" @selected(old('estatus_operativo') == 'baja')>Baja</option>
                            <option value="traslado" @selected(old('estatus_operativo') == 'traslado')>Traslado</option>
                        </select>
                    </label>

                    <label class="span-4">
                        <span>Descripción del bien</span>
                        <input name="descripcion" value="{{ old('descripcion') }}">
                    </label>
                </div>
            </fieldset>

            <fieldset class="compact-panel panel-control">
                <legend>Control, ubicación y responsable</legend>
                <div class="compact-field-grid compact-field-grid-4">
                    <label>
                        <span>Centro de costo</span>
                        <select name="centro_costo_id">
                            <option value="">Seleccione...</option>
                            @foreach ($centrosCosto as $item)
                                <option value="{{ $item->id }}" @selected(old('centro_costo_id') == $item->id)>{{ $item->label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Planta o sucursal</span>
                        <select name="planta_id">
                            <option value="">Seleccione...</option>
                            @foreach ($plantas as $item)
                                <option value="{{ $item->id }}" @selected(old('planta_id') == $item->id)>{{ $item->label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Ubicación física</span>
                        <select name="ubicacion_id">
                            <option value="">Seleccione...</option>
                            @foreach ($ubicaciones as $item)
                                <option value="{{ $item->id }}" @selected(old('ubicacion_id') == $item->id)>{{ $item->label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Responsable</span>
                        <select name="responsable_id">
                            <option value="">Seleccione...</option>
                            @foreach ($responsables as $item)
                                <option value="{{ $item->id }}" @selected(old('responsable_id') == $item->id)>{{ $item->label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </fieldset>

            <fieldset class="compact-panel panel-documentos">
                <legend>Documentos y observaciones</legend>
                <div class="compact-field-grid compact-field-grid-2">
                    <label>
                        <span>Documento PDF/XML (separa con comas)</span>
                        <input name="documentos_referencia" value="{{ old('documentos_referencia') }}" placeholder="factura_184.pdf, factura_184.xml">
                    </label>

                    <label>
                        <span>Observaciones</span>
                        <textarea name="observaciones">{{ old('observaciones') }}</textarea>
                    </label>
                </div>
            </fieldset>
        </div>
    </form>
</section>

@endsection
