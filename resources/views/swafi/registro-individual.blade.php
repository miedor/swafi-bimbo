@extends('layouts.app')

@section('title', 'Registro individual | SWAFI')
@section('page_title', 'Registro individual')
@section('page_subtitle', 'Captura manual de expedientes de activo fijo')
@section('breadcrumb', 'Registro individual')
@section('content')

<section class="card form-card">
    <div class="section-title">
        <h2>Registro individual de expedientes</h2>
        <div>
            <a class="btn btn-secondary" href="{{ route('dashboard') }}">Volver al dashboard</a>
        </div>
    </div>

    @if (session('success'))
        <div style="margin-bottom:16px; padding:12px 16px; background:#e8f7ea; color:#1f6b2a; border-radius:12px;">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div style="margin-bottom:16px; padding:12px 16px; background:#fdeaea; color:#8a1f1f; border-radius:12px;">
            <strong>Se encontraron errores:</strong>
            <ul style="margin:8px 0 0 18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('registro-individual.store') }}">
        @csrf

        <div class="form-row three">
            <label>
                <span>Folio de factura</span>
                <input name="folio_factura" value="{{ old('folio_factura') }}">
            </label>

            <label>
                <span>UUID CFDI</span>
                <input name="uuid_cfdi" value="{{ old('uuid_cfdi') }}">
            </label>

            <label>
                <span>Fecha de factura</span>
                <input type="date" name="fecha_factura" value="{{ old('fecha_factura') }}">
            </label>
        </div>

        <div class="form-row three">
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
                <span>Proveedor</span>
                <select name="proveedor_id">
                    <option value="">Seleccione...</option>
                    @foreach ($proveedores as $item)
                        <option value="{{ $item->id }}" @selected(old('proveedor_id') == $item->id)>{{ $item->label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="form-row three">
            <label>
                <span>Descripción del bien</span>
                <input name="descripcion" value="{{ old('descripcion') }}">
            </label>

            <label>
                <span>Serie</span>
                <input name="serie" value="{{ old('serie') }}">
            </label>

            <label>
                <span>Marca</span>
                <input name="marca" value="{{ old('marca') }}">
            </label>
        </div>

        <div class="form-row three">
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
        </div>

        <div class="form-row three">
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

            <label>
                <span>Centro de costo</span>
                <select name="centro_costo_id">
                    <option value="">Seleccione...</option>
                    @foreach ($centrosCosto as $item)
                        <option value="{{ $item->id }}" @selected(old('centro_costo_id') == $item->id)>{{ $item->label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="form-row three">
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

        <div class="form-row">
            <label>
                <span>Documento PDF/XML (separa con comas)</span>
                <input name="documentos_referencia" value="{{ old('documentos_referencia') }}" placeholder="factura_184.pdf, factura_184.xml">
            </label>
        </div>

        <label>
            <span>Observaciones</span>
            <textarea name="observaciones">{{ old('observaciones') }}</textarea>
        </label>

        <div class="tabs">
            <button type="submit" class="tab" style="border:none; cursor:pointer;">Guardar</button>
            <a class="tab" href="{{ route('registro-individual') }}">Limpiar</a>
            <span class="tab">Vista previa</span>
        </div>
    </form>
</section>

@endsection
