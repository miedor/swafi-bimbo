@extends('layouts.app')

@section('title', 'Registro individual | SWAFI')
@section('page_title', 'Registro individual')
@section('page_subtitle', 'Captura manual de expedientes de activo fijo')
@section('breadcrumb', 'Registro individual')
@section('content')

<section class="ri-page card">
    <div class="ri-header">
        <div>
            <p class="ri-eyebrow">M01 Gestión de expedientes</p>
            <h2>Registro individual de expediente</h2>
            <p class="ri-help">Capture los datos mínimos del activo, factura, ubicación y soporte documental en una sola vista compacta.</p>
        </div>

        <div class="ri-actions" aria-label="Acciones principales del registro individual">
            <button type="submit" form="registro-individual-form" class="btn btn-primary">Guardar</button>
            <a class="btn btn-secondary" href="{{ route('registro-individual') }}">Limpiar</a>
            <a class="btn btn-light" href="{{ route('dashboard') }}">Dashboard</a>
        </div>
    </div>

    @if (session('success'))
        <div class="ri-alert ri-alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="ri-alert ri-alert-error">
            <strong>Se encontraron errores:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="registro-individual-form" class="ri-form" method="POST" action="{{ route('registro-individual.store') }}">
        @csrf

        <div class="ri-grid">
            <article class="ri-panel ri-panel-factura">
                <div class="ri-panel-title">
                    <span class="ri-panel-number">1</span>
                    <div>
                        <h3>Factura</h3>
                        <p>Datos fiscales y monetarios</p>
                    </div>
                </div>

                <div class="ri-fields ri-fields-2">
                    <label class="ri-field">
                        <span>Folio de factura <b>*</b></span>
                        <input name="folio_factura" value="{{ old('folio_factura') }}" placeholder="Ej. FAC-000184">
                    </label>

                    <label class="ri-field">
                        <span>UUID CFDI</span>
                        <input name="uuid_cfdi" value="{{ old('uuid_cfdi') }}" placeholder="UUID del comprobante">
                    </label>

                    <label class="ri-field">
                        <span>Fecha factura <b>*</b></span>
                        <input type="date" name="fecha_factura" value="{{ old('fecha_factura') }}">
                    </label>

                    <label class="ri-field">
                        <span>Monto fiscal <b>*</b></span>
                        <input type="number" step="0.01" name="monto_factura" value="{{ old('monto_factura') }}" placeholder="0.00">
                    </label>

                    <label class="ri-field">
                        <span>Moneda <b>*</b></span>
                        <select name="moneda">
                            <option value="MXN" @selected(old('moneda', 'MXN') == 'MXN')>MXN</option>
                            <option value="USD" @selected(old('moneda') == 'USD')>USD</option>
                            <option value="EUR" @selected(old('moneda') == 'EUR')>EUR</option>
                        </select>
                    </label>

                    <label class="ri-field">
                        <span>Proveedor <b>*</b></span>
                        <select name="proveedor_id">
                            <option value="">Seleccione...</option>
                            @foreach ($proveedores as $item)
                                <option value="{{ $item->id }}" @selected(old('proveedor_id') == $item->id)>{{ $item->label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </article>

            <article class="ri-panel ri-panel-activo">
                <div class="ri-panel-title">
                    <span class="ri-panel-number">2</span>
                    <div>
                        <h3>Activo fijo</h3>
                        <p>Identificación del bien</p>
                    </div>
                </div>

                <div class="ri-fields ri-fields-2">
                    <label class="ri-field ri-field-wide">
                        <span>Número de activo fijo <b>*</b></span>
                        <input name="numero_activo" value="{{ old('numero_activo') }}" placeholder="Ej. BIM-000001">
                    </label>

                    <label class="ri-field">
                        <span>Tipo de activo <b>*</b></span>
                        <select name="tipo_activo_id">
                            <option value="">Seleccione...</option>
                            @foreach ($tiposActivo as $item)
                                <option value="{{ $item->id }}" @selected(old('tipo_activo_id') == $item->id)>{{ $item->label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ri-field">
                        <span>Estatus <b>*</b></span>
                        <select name="estatus_operativo">
                            <option value="en_operacion" @selected(old('estatus_operativo') == 'en_operacion')>En operación</option>
                            <option value="baja" @selected(old('estatus_operativo') == 'baja')>Baja</option>
                            <option value="traslado" @selected(old('estatus_operativo') == 'traslado')>Traslado</option>
                        </select>
                    </label>

                    <label class="ri-field">
                        <span>Serie</span>
                        <input name="serie" value="{{ old('serie') }}" placeholder="Serie">
                    </label>

                    <label class="ri-field">
                        <span>Marca</span>
                        <input name="marca" value="{{ old('marca') }}" placeholder="Marca">
                    </label>

                    <label class="ri-field">
                        <span>Modelo</span>
                        <input name="modelo" value="{{ old('modelo') }}" placeholder="Modelo">
                    </label>

                    <label class="ri-field">
                        <span>Fecha adquisición</span>
                        <input type="date" name="fecha_adquisicion" value="{{ old('fecha_adquisicion') }}">
                    </label>

                    <label class="ri-field ri-field-wide">
                        <span>Descripción del bien <b>*</b></span>
                        <input name="descripcion" value="{{ old('descripcion') }}" placeholder="Descripción breve del activo">
                    </label>
                </div>
            </article>

            <article class="ri-panel ri-panel-control">
                <div class="ri-panel-title">
                    <span class="ri-panel-number">3</span>
                    <div>
                        <h3>Control y documentos</h3>
                        <p>Ubicación, responsable y soporte</p>
                    </div>
                </div>

                <div class="ri-fields">
                    <div class="ri-fields ri-fields-2 ri-nested-grid">
                        <label class="ri-field">
                            <span>Centro de costo <b>*</b></span>
                            <select name="centro_costo_id">
                                <option value="">Seleccione...</option>
                                @foreach ($centrosCosto as $item)
                                    <option value="{{ $item->id }}" @selected(old('centro_costo_id') == $item->id)>{{ $item->label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="ri-field">
                            <span>Planta o sucursal <b>*</b></span>
                            <select name="planta_id">
                                <option value="">Seleccione...</option>
                                @foreach ($plantas as $item)
                                    <option value="{{ $item->id }}" @selected(old('planta_id') == $item->id)>{{ $item->label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="ri-field">
                            <span>Ubicación física</span>
                            <select name="ubicacion_id">
                                <option value="">Seleccione...</option>
                                @foreach ($ubicaciones as $item)
                                    <option value="{{ $item->id }}" @selected(old('ubicacion_id') == $item->id)>{{ $item->label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="ri-field">
                            <span>Responsable</span>
                            <select name="responsable_id">
                                <option value="">Seleccione...</option>
                                @foreach ($responsables as $item)
                                    <option value="{{ $item->id }}" @selected(old('responsable_id') == $item->id)>{{ $item->label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <label class="ri-field">
                        <span>Documento PDF/XML</span>
                        <input name="documentos_referencia" value="{{ old('documentos_referencia') }}" placeholder="factura_184.pdf, factura_184.xml">
                    </label>

                    <label class="ri-field">
                        <span>Observaciones</span>
                        <textarea name="observaciones" placeholder="Notas, pendientes o aclaraciones del expediente">{{ old('observaciones') }}</textarea>
                    </label>
                </div>
            </article>
        </div>
    </form>
</section>

@endsection
