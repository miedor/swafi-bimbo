@extends('layouts.app')

@section('title', 'Registro individual | SWAFI')
@section('page_title', 'Registro individual')
@section('page_subtitle', 'Captura manual de expedientes de activo fijo')
@section('breadcrumb', 'Registro individual')

@section('page_styles')
<style nonce="{{ request()->attributes->get('csp_nonce') }}">
/* =========================================================
   SWAFI - Registro individual compacto profesional
   Objetivo: reducir scroll sin sacrificar diseño visual
   ========================================================= */

.main {
    padding-top: 18px !important;
    padding-bottom: 18px !important;
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
    min-width: 220px;
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

.ri-field input[type="file"] {
    height: auto;
    min-height: 44px;
    padding: 10px;
    cursor: pointer;
    background: #ffffff;
}

.ri-field input[type="file"]::file-selector-button {
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

.ri-field input[type="file"]::file-selector-button:hover {
    background: #103f7d;
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


.ri-asset-selector {
    margin: 0 0 14px;
    padding: 14px;
    border: 1px solid #bfd5ef;
    border-radius: 17px;
    background: linear-gradient(135deg, #f7fbff 0%, #eef6ff 100%);
}

.ri-asset-selector-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 10px;
}

.ri-asset-selector-head h3 {
    margin: 0;
    color: #12345a;
    font-size: 15px;
    font-weight: 900;
}

.ri-asset-selector-head p {
    margin: 4px 0 0;
    color: #526a86;
    font-size: 12px;
    line-height: 1.35;
}

.ri-asset-search-row {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) auto auto;
    gap: 9px;
    align-items: end;
}

.ri-asset-status {
    min-height: 18px;
    margin: 9px 0 0;
    color: #526a86;
    font-size: 12px;
    font-weight: 700;
}

.ri-asset-status.is-success {
    color: #1f6b2a;
}

.ri-asset-status.is-error {
    color: #9b1c1c;
}

.ri-asset-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
    margin-top: 11px;
    padding-top: 11px;
    border-top: 1px solid #cfe0f3;
}

.ri-asset-summary[hidden] {
    display: none;
}

.ri-asset-summary-item {
    min-width: 0;
    padding: 8px 9px;
    border-radius: 11px;
    background: #ffffff;
    border: 1px solid #d8e5f3;
}

.ri-asset-summary-item strong,
.ri-asset-summary-item span {
    display: block;
}

.ri-asset-summary-item strong {
    margin-bottom: 3px;
    color: #506987;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.ri-asset-summary-item span {
    overflow-wrap: anywhere;
    color: #17385e;
    font-size: 12px;
    font-weight: 800;
}

.ri-existing-notice {
    margin: 0 0 9px;
    padding: 9px 10px;
    border-radius: 12px;
    background: #fff8df;
    border: 1px solid #efd98b;
    color: #6d5715;
    font-size: 12px;
    line-height: 1.4;
}

.ri-field input:disabled,
.ri-field select:disabled,
.ri-field textarea:disabled {
    cursor: not-allowed;
    background: #edf2f7;
    color: #44566c;
    border-color: #d7e0ea;
    opacity: 1;
}

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

@media (max-width: 980px) {
    .ri-asset-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

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

    .ri-asset-search-row,
    .ri-asset-summary {
        grid-template-columns: 1fr;
    }

    .ri-asset-selector-head {
        display: block;
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
    <form
        method="POST"
        action="{{ route('registro-individual.store') }}"
        enctype="multipart/form-data"
        data-registration-form
    >
        @csrf
        <input type="hidden" name="asset_mode" value="{{ old('asset_mode', 'new') }}" data-asset-mode>

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

        <section
            class="ri-asset-selector"
            data-asset-selector
            data-lookup-url="{{ route('registro-individual.activo') }}"
        >
            <div class="ri-asset-selector-head">
                <div>
                    <h3>Selecciona el origen del activo</h3>
                    <p>
                        Busca un activo vigente para asociarle otro expediente sin modificar sus datos maestros,
                        o captura un número nuevo para darlo de alta.
                    </p>
                </div>
            </div>

            <div class="ri-asset-search-row">
                <label class="ri-field">
                    <span>Número de activo <b>*</b></span>
                    <input
                        name="numero_activo"
                        value="{{ old('numero_activo') }}"
                        placeholder="Ej. BIM-000001"
                        autocomplete="off"
                        data-asset-number
                    >
                </label>

                <button type="button" class="ri-btn ri-btn-primary" data-asset-search>
                    Buscar activo existente
                </button>

                <button type="button" class="ri-btn ri-btn-soft" data-asset-new>
                    Registrar activo nuevo
                </button>
            </div>

            <p class="ri-asset-status" data-asset-status role="status" aria-live="polite">
                Captura el número y selecciona una opción.
            </p>

            <div class="ri-asset-summary" data-asset-summary hidden>
                <div class="ri-asset-summary-item">
                    <strong>Activo seleccionado</strong>
                    <span data-summary-number></span>
                </div>
                <div class="ri-asset-summary-item">
                    <strong>Tipo</strong>
                    <span data-summary-type></span>
                </div>
                <div class="ri-asset-summary-item">
                    <strong>Planta</strong>
                    <span data-summary-plant></span>
                </div>
                <div class="ri-asset-summary-item">
                    <strong>Expedientes vigentes</strong>
                    <span data-summary-expedientes></span>
                </div>
            </div>
        </section>

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
                                @foreach($monedas as $moneda)
                                    <option value="{{ $moneda->clave }}" @selected(old('moneda', 'MXN') === $moneda->clave)>
                                        {{ $moneda->clave }} — {{ $moneda->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <label class="ri-field">
                        <span>Proveedor <b>*</b></span>
                        <select name="proveedor_id" data-asset-field>
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
                    <div class="ri-existing-notice" data-existing-asset-notice hidden>
                        El activo existente se muestra en modo protegido. Al guardar se creará únicamente el nuevo expediente y no se modificarán sus datos maestros.
                    </div>

                    <div class="ri-fields ri-fields-2">
                        <label class="ri-field">
                            <span>Tipo de activo <b>*</b></span>
                            <select name="tipo_activo_id" data-asset-field>
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
                            <select name="estatus_operativo" required data-asset-field>
                                <option value="">Seleccione...</option>
                                @foreach ($estatusOperativos as $estatusOperativo)
                                    <option
                                        value="{{ $estatusOperativo->clave }}"
                                        @selected(old('estatus_operativo', 'en_operacion') === $estatusOperativo->clave)
                                    >
                                        {{ $estatusOperativo->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="ri-fields ri-fields-2">
                        <label class="ri-field">
                            <span>Serie</span>
                            <input name="serie" value="{{ old('serie') }}" placeholder="Serie" data-asset-field>
                        </label>

                        <label class="ri-field">
                            <span>Marca</span>
                            <input name="marca" value="{{ old('marca') }}" placeholder="Marca" data-asset-field>
                        </label>
                    </div>

                    <div class="ri-fields ri-fields-2">
                        <label class="ri-field">
                            <span>Modelo</span>
                            <input name="modelo" value="{{ old('modelo') }}" placeholder="Modelo" data-asset-field>
                        </label>

                        <label class="ri-field">
                            <span>Fecha adquisición</span>
                            <input type="date" name="fecha_adquisicion" value="{{ old('fecha_adquisicion') }}" data-asset-field>
                        </label>
                    </div>

                    <label class="ri-field">
                        <span>Descripción del bien <b>*</b></span>
                        <textarea name="descripcion" placeholder="Descripción breve del activo" data-asset-field>{{ old('descripcion') }}</textarea>
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
                        <select name="centro_costo_id" data-asset-field>
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
                        <select name="planta_id" data-asset-field>
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
                        <select name="ubicacion_id" data-asset-field>
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
                        <select name="responsable_id" data-asset-field>
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
                        <input
                            type="file"
                            name="documentos[]"
                            multiple
                            accept=".pdf,.xml,application/pdf,text/xml,application/xml"
                        >
                    </label>

                    <label class="ri-field ri-field-wide">
                        <span>Observaciones</span>
                        <textarea name="observaciones" placeholder="Notas, aclaraciones o seguimiento del expediente">{{ old('observaciones') }}</textarea>
                    </label>
                </div>

                <div class="ri-help">
                    Selecciona el PDF de la factura y el XML CFDI. SWAFI resguardará los archivos y registrará nombre, tipo, tamaño, ruta, versión y hash SHA-256 para trazabilidad documental.
                </div>
            </section>

        </div>
    </form>
</section>

@endsection

@section('page_scripts')
<script
    nonce="{{ request()->attributes->get('csp_nonce') }}"
    src="{{ asset('assets/swafi/js/swafi-registro-individual.js') }}?v={{ filemtime(public_path('assets/swafi/js/swafi-registro-individual.js')) }}"
></script>
@endsection
