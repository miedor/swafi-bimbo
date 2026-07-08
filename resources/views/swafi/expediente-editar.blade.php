@extends('layouts.app')

@section('title', 'Editar expediente | SWAFI')
@section('page_title', 'Editar expediente')
@section('page_subtitle', 'Actualización controlada de datos del activo y factura')
@section('breadcrumb', 'Editar expediente')

@section('page_styles')
<style>
  .edit-shell {
    background:#fff;
    border:1px solid #dce7f5;
    border-radius:22px;
    box-shadow:0 16px 36px rgba(15,23,42,.07);
    padding:18px;
  }

  .edit-header {
    display:grid;
    grid-template-columns:1fr auto;
    gap:16px;
    align-items:start;
    padding-bottom:14px;
    margin-bottom:14px;
    border-bottom:1px solid #e6eef8;
  }

  .edit-eyebrow {
    margin:0 0 5px;
    color:#17559e;
    font-size:12px;
    font-weight:900;
    letter-spacing:.08em;
    text-transform:uppercase;
  }

  .edit-title {
    margin:0;
    color:#102a4c;
    font-size:22px;
    line-height:1.15;
    font-weight:900;
  }

  .edit-subtitle {
    margin:6px 0 0;
    color:#64748b;
    font-size:13px;
    line-height:1.35;
  }

  .edit-actions {
    display:flex;
    gap:8px;
    justify-content:flex-end;
    align-items:center;
    flex-wrap:wrap;
    min-width:260px;
  }

  .edit-btn {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:38px;
    padding:9px 17px;
    border-radius:13px;
    border:1px solid transparent;
    text-decoration:none;
    cursor:pointer;
    font-size:13px;
    font-weight:900;
    line-height:1;
    transition:all .15s ease;
  }

  .edit-btn-primary { background:#154f9b;color:#fff;border-color:#154f9b; }
  .edit-btn-primary:hover { background:#103f7d; }
  .edit-btn-soft { background:#edf4ff;color:#154f9b;border-color:#edf4ff; }

  .edit-message {
    margin:0 0 12px;
    padding:10px 12px;
    border-radius:13px;
    font-size:13px;
    line-height:1.35;
  }

  .edit-message-error { background:#fdeaea;color:#8a1f1f;border:1px solid #f2baba; }
  .edit-message ul { margin:6px 0 0 18px; }

  .edit-grid {
    display:grid;
    grid-template-columns:1fr 1fr 1fr;
    gap:14px;
    align-items:start;
  }

  .edit-panel {
    min-width:0;
    background:#f8fbff;
    border:1px solid #dfe9f6;
    border-radius:18px;
    padding:14px;
  }

  .edit-panel-head {
    display:flex;
    align-items:center;
    gap:10px;
    margin-bottom:12px;
  }

  .edit-number {
    width:30px;height:30px;min-width:30px;
    border-radius:10px;
    background:#154f9b;color:#fff;
    display:inline-flex;align-items:center;justify-content:center;
    font-size:13px;font-weight:900;
  }

  .edit-panel-head h3 { margin:0;color:#12345a;font-size:15px;line-height:1.1;font-weight:900; }
  .edit-panel-head p { margin:2px 0 0;color:#64748b;font-size:11px;line-height:1.2; }

  .edit-fields { display:grid;grid-template-columns:1fr;gap:9px; }
  .edit-fields-2 { grid-template-columns:repeat(2,minmax(0,1fr)); }
  .edit-field { display:block;min-width:0;margin:0; }
  .edit-field-wide { grid-column:1 / -1; }
  .edit-field span { display:block;margin:0 0 4px;color:#1d3558;font-size:12px;line-height:1.15;font-weight:900; }
  .edit-field span b { color:#dc2626; }

  .edit-field input,
  .edit-field select,
  .edit-field textarea {
    width:100%;height:38px;min-height:38px;
    padding:8px 10px;
    border:1px solid #d5e1ef;
    border-radius:11px;
    background:#fff;
    color:#16304d;
    font-size:13px;
    line-height:1.2;
    box-shadow:none;
  }

  .edit-field input[readonly] { background:#edf4ff;color:#475569;font-weight:800; }
  .edit-field textarea { height:72px;min-height:72px;resize:vertical; }

  .edit-help {
    margin-top:8px;
    padding:9px 10px;
    border-radius:13px;
    background:#eef6ff;
    color:#385b82;
    font-size:12px;
    line-height:1.35;
  }

  @media (max-width:1280px) { .edit-grid { grid-template-columns:1fr 1fr; } .edit-panel-control { grid-column:1 / -1; } }
  @media (max-width:980px) { .edit-header { grid-template-columns:1fr; } .edit-actions { justify-content:flex-start;min-width:0; } .edit-grid { grid-template-columns:1fr; } }
  @media (max-width:680px) { .edit-fields-2 { grid-template-columns:1fr; } }
</style>
@endsection

@section('content')

<section class="edit-shell">
  <form method="POST" action="{{ route('expedientes.actualizar', $expediente->expediente_id) }}">
    @csrf
    @method('PUT')

    <div class="edit-header">
      <div>
        <p class="edit-eyebrow">M01 Gestión de expedientes</p>
        <h2 class="edit-title">Editar expediente {{ $expediente->folio_factura }}</h2>
        <p class="edit-subtitle">
          Actualiza datos del expediente y del activo. La carga o eliminación de PDF/XML se realiza desde el detalle del expediente.
        </p>
      </div>

      <div class="edit-actions">
        <button type="submit" class="edit-btn edit-btn-primary">Actualizar</button>
        <a class="edit-btn edit-btn-soft" href="{{ route('expediente', $expediente->expediente_id) }}">Volver al detalle</a>
        <a class="edit-btn edit-btn-soft" href="{{ route('busqueda', ['numero_activo' => $expediente->numero_activo]) }}">Búsqueda</a>
      </div>
    </div>

    @if ($errors->any())
      <div class="edit-message edit-message-error">
        <strong>Se encontraron errores:</strong>
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="edit-grid">
      <section class="edit-panel">
        <div class="edit-panel-head">
          <span class="edit-number">1</span>
          <div>
            <h3>Factura</h3>
            <p>Datos fiscales y monetarios</p>
          </div>
        </div>

        <div class="edit-fields">
          <label class="edit-field">
            <span>Folio de factura <b>*</b></span>
            <input name="folio_factura" value="{{ old('folio_factura', $expediente->folio_factura) }}" placeholder="Ej. FAC-000184">
          </label>

          <label class="edit-field">
            <span>UUID CFDI</span>
            <input name="uuid_cfdi" value="{{ old('uuid_cfdi', $expediente->uuid_cfdi) }}" placeholder="UUID del comprobante">
          </label>

          <label class="edit-field">
            <span>Fecha factura <b>*</b></span>
            <input type="date" name="fecha_factura" value="{{ old('fecha_factura', $expediente->fecha_factura) }}">
          </label>

          <div class="edit-fields edit-fields-2">
            <label class="edit-field">
              <span>Monto fiscal <b>*</b></span>
              <input type="number" step="0.01" name="monto_factura" value="{{ old('monto_factura', $expediente->monto_factura) }}" placeholder="0.00">
            </label>

            <label class="edit-field">
              <span>Moneda <b>*</b></span>
              <select name="moneda">
                <option value="MXN" {{ old('moneda', $expediente->moneda) == 'MXN' ? 'selected' : '' }}>MXN</option>
                <option value="USD" {{ old('moneda', $expediente->moneda) == 'USD' ? 'selected' : '' }}>USD</option>
                <option value="EUR" {{ old('moneda', $expediente->moneda) == 'EUR' ? 'selected' : '' }}>EUR</option>
              </select>
            </label>
          </div>

          <label class="edit-field">
            <span>Proveedor <b>*</b></span>
            <select name="proveedor_id">
              <option value="">Seleccione...</option>
              @foreach ($proveedores as $item)
                <option value="{{ $item->id }}" {{ old('proveedor_id', $expediente->proveedor_id) == $item->id ? 'selected' : '' }}>
                  {{ $item->label }}
                </option>
              @endforeach
            </select>
          </label>
        </div>
      </section>

      <section class="edit-panel">
        <div class="edit-panel-head">
          <span class="edit-number">2</span>
          <div>
            <h3>Activo fijo</h3>
            <p>Identificación del bien</p>
          </div>
        </div>

        <div class="edit-fields">
          <label class="edit-field">
            <span>Número de activo</span>
            <input name="numero_activo_visible" value="{{ $expediente->numero_activo }}" readonly>
          </label>

          <div class="edit-fields edit-fields-2">
            <label class="edit-field">
              <span>Tipo de activo <b>*</b></span>
              <select name="tipo_activo_id">
                <option value="">Seleccione...</option>
                @foreach ($tiposActivo as $item)
                  <option value="{{ $item->id }}" {{ old('tipo_activo_id', $expediente->tipo_activo_id) == $item->id ? 'selected' : '' }}>
                    {{ $item->label }}
                  </option>
                @endforeach
              </select>
            </label>

            <label class="edit-field">
              <span>Estatus <b>*</b></span>
              <select name="estatus_operativo">
                <option value="en_operacion" {{ old('estatus_operativo', $expediente->estatus_operativo) == 'en_operacion' ? 'selected' : '' }}>En operación</option>
                <option value="baja" {{ old('estatus_operativo', $expediente->estatus_operativo) == 'baja' ? 'selected' : '' }}>Baja</option>
                <option value="traslado" {{ old('estatus_operativo', $expediente->estatus_operativo) == 'traslado' ? 'selected' : '' }}>Traslado</option>
              </select>
            </label>
          </div>

          <div class="edit-fields edit-fields-2">
            <label class="edit-field">
              <span>Serie</span>
              <input name="serie" value="{{ old('serie', $expediente->serie) }}" placeholder="Serie">
            </label>

            <label class="edit-field">
              <span>Marca</span>
              <input name="marca" value="{{ old('marca', $expediente->marca) }}" placeholder="Marca">
            </label>
          </div>

          <div class="edit-fields edit-fields-2">
            <label class="edit-field">
              <span>Modelo</span>
              <input name="modelo" value="{{ old('modelo', $expediente->modelo) }}" placeholder="Modelo">
            </label>

            <label class="edit-field">
              <span>Fecha adquisición</span>
              <input type="date" name="fecha_adquisicion" value="{{ old('fecha_adquisicion', $expediente->fecha_adquisicion) }}">
            </label>
          </div>

          <label class="edit-field">
            <span>Descripción del bien <b>*</b></span>
            <textarea name="descripcion" placeholder="Descripción breve del activo">{{ old('descripcion', $expediente->activo_descripcion) }}</textarea>
          </label>
        </div>
      </section>

      <section class="edit-panel edit-panel-control">
        <div class="edit-panel-head">
          <span class="edit-number">3</span>
          <div>
            <h3>Control</h3>
            <p>Ubicación, responsable y observaciones</p>
          </div>
        </div>

        <div class="edit-fields">
          <label class="edit-field">
            <span>Centro de costo <b>*</b></span>
            <select name="centro_costo_id">
              <option value="">Seleccione...</option>
              @foreach ($centrosCosto as $item)
                <option value="{{ $item->id }}" {{ old('centro_costo_id', $expediente->centro_costo_id) == $item->id ? 'selected' : '' }}>
                  {{ $item->label }}
                </option>
              @endforeach
            </select>
          </label>

          <label class="edit-field">
            <span>Planta o sucursal <b>*</b></span>
            <select name="planta_id">
              <option value="">Seleccione...</option>
              @foreach ($plantas as $item)
                <option value="{{ $item->id }}" {{ old('planta_id', $expediente->planta_id) == $item->id ? 'selected' : '' }}>
                  {{ $item->label }}
                </option>
              @endforeach
            </select>
          </label>

          <label class="edit-field">
            <span>Ubicación física</span>
            <select name="ubicacion_id">
              <option value="">Seleccione...</option>
              @foreach ($ubicaciones as $item)
                <option value="{{ $item->id }}" {{ old('ubicacion_id', $expediente->ubicacion_id) == $item->id ? 'selected' : '' }}>
                  {{ $item->label }}
                </option>
              @endforeach
            </select>
          </label>

          <label class="edit-field">
            <span>Responsable</span>
            <select name="responsable_id">
              <option value="">Seleccione...</option>
              @foreach ($responsables as $item)
                <option value="{{ $item->id }}" {{ old('responsable_id', $expediente->responsable_id) == $item->id ? 'selected' : '' }}>
                  {{ $item->label }}
                </option>
              @endforeach
            </select>
          </label>

          <label class="edit-field edit-field-wide">
            <span>Observaciones</span>
            <textarea name="observaciones" placeholder="Notas, aclaraciones o seguimiento del expediente">{{ old('observaciones', $expediente->observaciones) }}</textarea>
          </label>
        </div>

        <div class="edit-help">
          El número de activo queda bloqueado para evitar duplicidades BIM. Para agregar, reemplazar o eliminar PDF/XML entra al detalle del expediente.
        </div>
      </section>
    </div>
  </form>
</section>

@endsection
