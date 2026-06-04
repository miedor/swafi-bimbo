@extends('layouts.app')

@section('title', 'Registro individual | SWAFI')
@section('page_title', 'Registro individual')
@section('page_subtitle', 'Captura manual de expedientes de activo fijo')
@section('breadcrumb', 'Registro individual')

@section('page_styles')
<style>
/* =========================================================
   SWAFI - Registro individual compacto profesional
   Objetivo: reducir scroll sin sacrificar diseño visual
   ========================================================= */

.main {
    padding-top: 18px !important;
    padding-bottom: 18px !important;
}

.topbar {
    margin-bottom: 6px !important;
}

.topbar h1 {
    font-size: 28px !important;
    line-height: 1.1 !important;
    margin-bottom: 4px !important;
}

.topbar p {
    font-size: 13px !important;
    margin: 0 !important;
}

.breadcrumb {
    margin-bottom: 10px !important;
    font-size: 12px !important;
}

.ri-shell {
    background: #ffffff;
    border: 1px solid #dce7f5;
    border-radius: 22px;
    box-shadow: 0 16px 36px rgba(15, 23, 42, 0.07);
    padding: 18px;
}

.ri-header {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 16px;
    align-items: start;
    padding-bottom: 14px;
    margin-bottom: 14px;
    border-bottom: 1px solid #e6eef8;
}

.ri-eyebrow {
    margin: 0 0 5px;
    color: #17559e;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.ri-title {
    margin: 0;
    color: #102a4c;
    font-size: 22px;
    line-height: 1.15;
    font-weight: 900;
}

.ri-subtitle {
    margin: 6px 0 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.35;
}

.ri-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    align-items: center;
    flex-wrap: wrap;
    min-width: 320px;
}

.ri-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 9px 17px;
    border-radius: 13px;
    border: 1px solid transparent;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
    font-weight: 900;
    line-height: 1;
    transition: all .15s ease;
}

.ri-btn-primary {
    background: #154f9b;
    color: #ffffff;
    border-color: #154f9b;
}

.ri-btn-primary:hover {
    background: #103f7d;
}

.ri-btn-soft {
    background: #edf4ff;
    color: #154f9b;
    border-color: #edf4ff;
}

.ri-btn-outline {
    background: #ffffff;
    color: #154f9b;
    border-color: #d7e4f4;
}

.ri-message {
    margin: 0 0 12px;
    padding: 10px 12px;
    border-radius: 13px;
    font-size: 13px;
    line-height: 1.35;
}

.ri-message-success {
    background: #e8f7ea;
    color: #1f6b2a;
    border: 1px solid #b9e5bf;
}

.ri-message-error {
    background: #fdeaea;
    color: #8a1f1f;
    border: 1px solid #f2baba;
}

.ri-message ul {
    margin: 6px 0 0 18px;
}

.ri-grid {
    display: grid;
    grid-template-columns: 0.95fr 1.05fr 1fr;
    gap: 14px;
    align-items: start;
}

.ri-panel {
    min-width: 0;
    background: #f8fbff;
    border: 1px solid #dfe9f6;
    border-radius: 18px;
    padding: 14px;
}

.ri-panel-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.ri-number {
    width: 30px;
    height: 30px;
    min-width: 30px;
    border-radius: 10px;
    background: #154f9b;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 900;
}

.ri-panel-head h3 {
    margin: 0;
    color: #12345a;
    font-size: 15px;
    line-height: 1.1;
    font-weight: 900;
}

.ri-panel-head p {
    margin: 2px 0 0;
    color: #64748b;
    font-size: 11px;
    line-height: 1.2;
}

.ri-fields {
    display: grid;
    grid-template-columns: 1fr;
    gap: 9px;
}

.ri-fields-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.ri-field {
    display: block;
    min-width: 0;
    margin: 0;
}

.ri-field-wide {
    grid-column: 1 / -1;
}

.ri-field span {
    display: block;
    margin: 0 0 4px;
    color: #1d3558;
    font-size: 12px;
    line-height: 1.15;
    font-weight: 900;
}

.ri-field span b {
    color: #dc2626;
}

.ri-field input,
.ri-field select,
.ri-field textarea {
    width: 100%;
    height: 38px;
    min-height: 38px;
    padding: 8px 10px;
    border: 1px solid #d5e1ef;
    border-radius: 11px;
    background: #ffffff;
    color: #16304d;
    font-size: 13px;
    line-height: 1.2;
    box-shadow: none;
}

.ri-field textarea {
    height: 66px;
    min-height: 66px;
    resize: vertical;
}

.ri-field input:focus,
.ri-field select:focus,
.ri-field textarea:focus {
    outline: none;
    border-color: #79a8e8;
    box-shadow: 0 0 0 3px rgba(21, 79, 155, 0.11);
}

.ri-help {
    margin-top: 8px;
    padding: 9px 10px;
    border-radius: 13px;
    background: #eef6ff;
    color: #385b82;
    font-size: 12px;
    line-height: 1.35;
}

.ri-footer-actions {
    display: none;
}

/* Pantallas medianas */
@media (max-width: 1280px) {
    .ri-grid {
        grid-template-columns: 1fr 1fr;
    }

    .ri-panel-control {
        grid-column: 1 / -1;
    }

    .ri-panel-control .ri-fields {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .ri-panel-control .ri-field-wide {
        grid-column: span 2;
    }
}

/* Tablets */
@media (max-width: 980px) {
    .ri-header {
        grid-template-columns: 1fr;
    }

    .ri-actions {
        min-width: 0;
        justify-content: flex-start;
    }

    .ri-grid {
        grid-template-columns: 1fr;
    }

    .ri-panel-control .ri-fields {
        grid-template-columns: 1fr 1fr;
    }
}

/* Celulares */
@media (max-width: 680px) {
    .main {
        padding: 12px !important;
    }

    .ri-shell {
        padding: 14px;
        border-radius: 18px;
    }

    .ri-title {
        font-size: 19px;
    }

    .ri-actions {
        width: 100%;
    }

    .ri-btn {
        flex: 1;
        padding-left: 10px;
        padding-right: 10px;
    }

    .ri-fields-2,
    .ri-panel-control .ri-fields {
        grid-template-columns: 1fr;
    }

    .ri-panel-control .ri-field-wide {
        grid-column: auto;
    }
}
</style>
@endsection

@section('content')

<section class="ri-shell">
    <form method="POST" action="{{ route('registro-individual.store') }}">
        @csrf

        <div class="ri-header">
            <div>
                <p class="ri-eyebrow">M01 Gestión de expedientes</p>
                <h2 class="ri-title">Registro individual de expediente</h2>
                <p class="ri-subtitle">
                    Capture los datos mínimos del activo, factura, ubicación y soporte documental en una vista compacta.
                </p>
            </div>

            <div class="ri-actions">
                <button type="submit" class="ri-btn ri-btn-primary">Guardar</button>
                <a class="ri-btn ri-btn-soft" href="{{ route('registro-individual') }}">Limpiar</a>
                <a class="ri-btn ri-btn-outline" href="{{ route('dashboard') }}">Dashboard</a>
            </div>
        </div>

        @if (session('success'))
            <div class="ri-message ri-message-success">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="ri-message ri-message-error">
                <strong>Se encontraron errores:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="ri-grid">

            {{-- PANEL 1: FACTURA --}}
            <section class="ri-panel">
                <div class="ri-panel-head">
                    <span class="ri-number">1</span>
                    <div>
                        <h3>Factura</h3>
                        <p>Datos fiscales y monetarios</p>
                    </div>
                </div>

                <div class="ri-fields">
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

                    <div class="ri-fields ri-fields-2">
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
                    </div>

                    <label class="ri-field">
                        <span>Proveedor <b>*</b></span>
                        <select name="proveedor_id">
                            <option value="">Seleccione...</option>
                            @foreach ($proveedores as $item)
                                <option value="{{ $item->id }}" @selected(old('proveedor_id') == $item->id)>
                                    {{ $item->label }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </section>

            {{-- PANEL 2: ACTIVO --}}
            <section class="ri-panel">
                <div class="ri-panel-head">
                    <span class="ri-number">2</span>
                    <div>
                        <h3>Activo fijo</h3>
                        <p>Identificación del bien</p>
                    </div>
                </div>

                <div class="ri-fields">
                    <label class="ri-field">
                        <span>Número de activo <b>*</b></span>
                        <input name="numero_activo" value="{{ old('numero_activo') }}" placeholder="Ej. BIM-000001">
                    </label>

                    <div class="ri-fields ri-fields-2">
                        <label class="ri-field">
                            <span>Tipo de activo <b>*</b></span>
                            <select name="tipo_activo_id">
                                <option value="">Seleccione...</option>
                                @foreach ($tiposActivo as $item)
                                    <option value="{{ $item->id }}" @selected(old('tipo_activo_id') == $item->id)>
                                        {{ $item->label }}
                                    </option>
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
                    </div>

                    <div class="ri-fields ri-fields-2">
                        <label class="ri-field">
                            <span>Serie</span>
                            <input name="serie" value="{{ old('serie') }}" placeholder="Serie">
                        </label>

                        <label class="ri-field">
                            <span>Marca</span>
                            <input name="marca" value="{{ old('marca') }}" placeholder="Marca">
                        </label>
                    </div>

                    <div class="ri-fields ri-fields-2">
                        <label class="ri-field">
                            <span>Modelo</span>
                            <input name="modelo" value="{{ old('modelo') }}" placeholder="Modelo">
                        </label>

                        <label class="ri-field">
                            <span>Fecha adquisición</span>
                            <input type="date" name="fecha_adquisicion" value="{{ old('fecha_adquisicion') }}">
                        </label>
                    </div>

                    <label class="ri-field">
                        <span>Descripción del bien <b>*</b></span>
                        <textarea name="descripcion" placeholder="Descripción breve del activo">{{ old('descripcion') }}</textarea>
                    </label>
                </div>
            </section>

            {{-- PANEL 3: CONTROL Y DOCUMENTOS --}}
            <section class="ri-panel ri-panel-control">
                <div class="ri-panel-head">
                    <span class="ri-number">3</span>
                    <div>
                        <h3>Control y documentos</h3>
                        <p>Ubicación, responsable y soporte PDF/XML</p>
                    </div>
                </div>

                <div class="ri-fields">
                    <label class="ri-field">
                        <span>Centro de costo <b>*</b></span>
                        <select name="centro_costo_id">
                            <option value="">Seleccione...</option>
                            @foreach ($centrosCosto as $item)
                                <option value="{{ $item->id }}" @selected(old('centro_costo_id') == $item->id)>
                                    {{ $item->label }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ri-field">
                        <span>Planta o sucursal <b>*</b></span>
                        <select name="planta_id">
                            <option value="">Seleccione...</option>
                            @foreach ($plantas as $item)
                                <option value="{{ $item->id }}" @selected(old('planta_id') == $item->id)>
                                    {{ $item->label }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ri-field">
                        <span>Ubicación física</span>
                        <select name="ubicacion_id">
                            <option value="">Seleccione...</option>
                            @foreach ($ubicaciones as $item)
                                <option value="{{ $item->id }}" @selected(old('ubicacion_id') == $item->id)>
                                    {{ $item->label }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ri-field">
                        <span>Responsable</span>
                        <select name="responsable_id">
                            <option value="">Seleccione...</option>
                            @foreach ($responsables as $item)
                                <option value="{{ $item->id }}" @selected(old('responsable_id') == $item->id)>
                                    {{ $item->label }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ri-field ri-field-wide">
                        <span>Documentos PDF/XML</span>
                        <input name="documentos_referencia" value="{{ old('documentos_referencia') }}" placeholder="factura_184.pdf, factura_184.xml">
                    </label>

                    <label class="ri-field ri-field-wide">
                        <span>Observaciones</span>
                        <textarea name="observaciones" placeholder="Notas, aclaraciones o seguimiento del expediente">{{ old('observaciones') }}</textarea>
                    </label>
                </div>

                <div class="ri-help">
                    Un expediente se considera completo cuando tiene referencia de PDF y XML. Si falta alguno, SWAFI lo marcará como incompleto para seguimiento.
                </div>
            </section>

        </div>
    </form>
</section>

@endsection
