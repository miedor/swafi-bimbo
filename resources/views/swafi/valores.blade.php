@extends('layouts.app')

@section('title', 'Valores fiscales y financieros | SWAFI')
@section('page_title', 'Valores fiscales y financieros')
@section('page_subtitle', 'Control contable, moneda, tipo de cambio y conciliación contra CFDI')
@section('breadcrumb', 'Valores fiscales y financieros')

@section('page_styles')
<style>
  .vf-shell{display:grid;gap:16px}.vf-grid{display:grid;grid-template-columns:minmax(380px,.9fr) minmax(460px,1.1fr);gap:16px;align-items:start}
  .vf-card{padding:18px;border:1px solid #dbe7f6;border-radius:22px;background:#fff;box-shadow:0 12px 28px rgba(15,23,42,.06)}
  .vf-title{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px}.vf-title h2{margin:0;color:#152f52;font-size:18px;font-weight:950}
  .vf-form{display:grid;grid-template-columns:repeat(2,minmax(150px,1fr));gap:11px}.vf-field.full{grid-column:1/-1}.vf-field span{display:block;margin-bottom:5px;color:#1d3558;font-size:12px;font-weight:900}
  .vf-field input,.vf-field select,.vf-field textarea{width:100%;min-height:40px;padding:9px 11px;border:1px solid #d5e1ef;border-radius:12px;background:#fff;color:#16304d;font-size:13px;font-weight:750}.vf-field textarea{min-height:76px;resize:vertical}
  .vf-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:13px}.vf-message{padding:12px 14px;border-radius:14px;font-weight:800}.vf-success{background:#e8f7ea;border:1px solid #b9e5bf;color:#1f6b2a}.vf-error{background:#fff4d6;border:1px solid #facc15;color:#7a4b00}.vf-info{background:#eef6ff;border:1px solid #c8dcf7;color:#174f9a}
  .vf-import{margin-top:16px;padding:14px;border:1px dashed #b8cbe5;border-radius:16px;background:#f8fbff}.vf-import h3{margin:0 0 6px;color:#152f52;font-size:15px}.vf-import p{margin:0 0 11px;color:#64748b;font-size:12px;line-height:1.4}
  .vf-filters{display:grid;grid-template-columns:repeat(4,minmax(130px,1fr));gap:10px}.vf-table-wrap{overflow:auto;border:1px solid #e2ebf6;border-radius:18px}.vf-table-wrap table{min-width:1260px}.vf-table-wrap th{position:sticky;top:0;background:#f6faff;z-index:2}
  .vf-status{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:900}.vf-status.ok{background:#e8f7ea;color:#1f6b2a}.vf-status.warn{background:#fff4d6;color:#8a4b00}.vf-status.danger{background:#fff0ee;color:#b42318}.vf-details{max-width:290px;color:#64748b;font-size:11px;line-height:1.3}.vf-readonly{padding:14px;border:1px solid #c8dcf7;border-radius:16px;background:#eef6ff;color:#174f9a;font-weight:800;line-height:1.45}
  @media(max-width:1200px){.vf-grid{grid-template-columns:1fr}.vf-filters{grid-template-columns:repeat(2,1fr)}}@media(max-width:720px){.vf-form,.vf-filters{grid-template-columns:1fr}}
</style>
@endsection

@section('content')
<div class="vf-shell">
  @if(session('success'))
    <div class="vf-message vf-success">{{ session('success') }}</div>
  @endif

  @if(session('import_summary'))
    @php($summary = session('import_summary'))
    <div class="vf-message vf-info">
      <strong>Carga masiva:</strong>
      {{ $summary['procesados'] ?? 0 }} procesados,
      {{ $summary['insertados'] ?? 0 }} insertados,
      {{ $summary['actualizados'] ?? 0 }} actualizados y
      {{ $summary['rechazados'] ?? 0 }} rechazados.
      @if(!empty($summary['errores']))
        <ul>
          @foreach(array_slice($summary['errores'], 0, 15) as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      @endif
    </div>
  @endif

  @if($errors->any())
    <div class="vf-message vf-error">
      <strong>Corrige los siguientes datos:</strong>
      <ul>
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
      </ul>
    </div>
  @endif

  <section class="vf-grid">
    <div class="vf-card">
      <div class="vf-title">
        <h2>{{ $valorEdit ? 'Editar valores del activo' : 'Captura contable' }}</h2>
        <span class="pill {{ $canAdministrarValores ? 'ok' : 'warn' }}">{{ $canAdministrarValores ? 'Edición autorizada' : 'Solo lectura' }}</span>
      </div>

      @if($canAdministrarValores)
        <form method="POST" action="{{ route('valores.store') }}">
          @csrf
          @if($valorEdit)<input type="hidden" name="valor_id" value="{{ $valorEdit->valor_id }}">@endif

          @php
            $selectedAsset = old('numero_activo', $valorEdit->numero_activo ?? request('numero_activo', ''));
            $selectedCurrency = old('moneda', $valorEdit->moneda ?? 'MXN');
            $selectedStatus = old('estatus_contable', $valorEdit->estatus_contable ?? 'vigente');
          @endphp

          <div class="vf-form">
            <label class="vf-field full"><span>Activo fijo</span>
              <select name="numero_activo" required>
                <option value="">Seleccione...</option>
                @foreach($catalogos['activos'] as $activo)
                  <option value="{{ $activo->numero_activo }}" {{ $selectedAsset === $activo->numero_activo ? 'selected' : '' }}>{{ $activo->numero_activo }} — {{ $activo->descripcion }}</option>
                @endforeach
              </select>
            </label>

            <label class="vf-field"><span>Valor fiscal</span><input type="number" step="0.01" min="0" name="valor_fiscal" value="{{ old('valor_fiscal', $valorEdit->valor_fiscal ?? '') }}" required></label>
            <label class="vf-field"><span>Valor financiero</span><input type="number" step="0.01" min="0" name="valor_financiero" value="{{ old('valor_financiero', $valorEdit->valor_financiero ?? '') }}" required></label>
            <label class="vf-field"><span>Moneda</span><input name="moneda" maxlength="3" value="{{ $selectedCurrency }}" placeholder="MXN" required></label>
            <label class="vf-field"><span>Tipo de cambio</span><input type="number" step="0.000001" min="0" name="tipo_cambio" value="{{ old('tipo_cambio', $valorEdit->tipo_cambio ?? ($selectedCurrency === 'MXN' ? '1' : '')) }}"></label>
            <label class="vf-field"><span>Fecha tipo de cambio</span><input type="date" name="fecha_tipo_cambio" value="{{ old('fecha_tipo_cambio', !empty($valorEdit->fecha_tipo_cambio) ? \Illuminate\Support\Carbon::parse($valorEdit->fecha_tipo_cambio)->format('Y-m-d') : '') }}"></label>
            <label class="vf-field"><span>Origen tipo de cambio</span><input name="origen_tipo_cambio" value="{{ old('origen_tipo_cambio', $valorEdit->origen_tipo_cambio ?? '') }}" placeholder="Ej. CFDI / fuente corporativa"></label>
            <label class="vf-field"><span>Depreciación acumulada</span><input type="number" step="0.01" min="0" name="depreciacion_acumulada" value="{{ old('depreciacion_acumulada', $valorEdit->depreciacion_acumulada ?? '0.00') }}" required></label>
            <label class="vf-field"><span>Valor en libros</span><input type="number" step="0.01" min="0" name="valor_en_libros" value="{{ old('valor_en_libros', $valorEdit->valor_en_libros ?? '') }}" placeholder="Se calcula si queda vacío"></label>
            <label class="vf-field"><span>Vida útil (meses)</span><input type="number" min="1" max="1200" name="vida_util_meses" value="{{ old('vida_util_meses', $valorEdit->vida_util_meses ?? '') }}"></label>
            <label class="vf-field"><span>Fecha de corte</span><input type="date" name="fecha_corte" value="{{ old('fecha_corte', !empty($valorEdit->fecha_corte) ? \Illuminate\Support\Carbon::parse($valorEdit->fecha_corte)->format('Y-m-d') : now()->format('Y-m-d')) }}" required></label>
            <label class="vf-field full"><span>Estatus contable</span>
              <select name="estatus_contable" required>
                <option value="vigente" {{ $selectedStatus === 'vigente' ? 'selected' : '' }}>Vigente</option>
                <option value="en_revision" {{ $selectedStatus === 'en_revision' ? 'selected' : '' }}>En revisión</option>
                <option value="baja" {{ $selectedStatus === 'baja' ? 'selected' : '' }}>Baja</option>
              </select>
            </label>
            <label class="vf-field full"><span>Motivo del cambio {{ $valorEdit ? '(obligatorio)' : '' }}</span><textarea name="motivo_cambio" placeholder="Describe el origen, ajuste o conciliación realizada.">{{ old('motivo_cambio', '') }}</textarea></label>
          </div>

          <div class="vf-actions"><button class="tab" type="submit">{{ $valorEdit ? 'Actualizar y conciliar' : 'Guardar y conciliar' }}</button><a class="tab" href="{{ route('valores') }}">Limpiar</a></div>
        </form>

        <div class="vf-import">
          <h3>Carga masiva con conciliación CFDI</h3>
          <p>La importación valida moneda, tipo de cambio, montos, duplicidad y consistencia contra el XML vigente. Si una fila falla, queda rechazada sin alterar los registros correctos.</p>
          <form method="POST" action="{{ route('valores.importar') }}" enctype="multipart/form-data">
            @csrf
            <label class="vf-field"><span>Archivo CSV</span><input type="file" name="archivo_csv" accept=".csv,.txt" required></label>
            <div class="vf-actions"><button class="tab" type="submit">Importar CSV</button><a class="tab" href="{{ route('valores.plantilla') }}">Descargar plantilla</a></div>
          </form>
        </div>
      @else
        <div class="vf-readonly">Tu perfil puede consultar valores fiscales y financieros, pero no crearlos, editarlos, importarlos ni eliminarlos.</div>
      @endif
    </div>

    <div class="vf-card">
      <div class="vf-title"><h2>Filtros de consulta</h2><span class="pill ok">Paginación y exportación</span></div>
      <form method="GET" action="{{ route('valores') }}">
        <div class="vf-filters">
          <label class="vf-field"><span>Número de activo</span><input name="numero_activo" value="{{ $filtros['numero_activo'] ?? '' }}"></label>
          <label class="vf-field"><span>Planta</span><select name="planta_id"><option value="">Todas</option>@foreach($catalogos['plantas'] as $item)<option value="{{ $item->id }}" {{ (string)($filtros['planta_id'] ?? '') === (string)$item->id ? 'selected' : '' }}>{{ $item->nombre }}</option>@endforeach</select></label>
          <label class="vf-field"><span>Proveedor</span><select name="proveedor_id"><option value="">Todos</option>@foreach($catalogos['proveedores'] as $item)<option value="{{ $item->id }}" {{ (string)($filtros['proveedor_id'] ?? '') === (string)$item->id ? 'selected' : '' }}>{{ $item->nombre }}</option>@endforeach</select></label>
          <label class="vf-field"><span>Centro de costo</span><select name="centro_costo_id"><option value="">Todos</option>@foreach($catalogos['centrosCosto'] as $item)<option value="{{ $item->id }}" {{ (string)($filtros['centro_costo_id'] ?? '') === (string)$item->id ? 'selected' : '' }}>{{ $item->clave }}</option>@endforeach</select></label>
          <label class="vf-field"><span>Tipo de activo</span><select name="tipo_activo_id"><option value="">Todos</option>@foreach($catalogos['tiposActivo'] as $item)<option value="{{ $item->id }}" {{ (string)($filtros['tipo_activo_id'] ?? '') === (string)$item->id ? 'selected' : '' }}>{{ $item->descripcion }}</option>@endforeach</select></label>
          <label class="vf-field"><span>Estatus contable</span><select name="estatus_contable"><option value="">Todos</option><option value="vigente" {{ ($filtros['estatus_contable'] ?? '') === 'vigente' ? 'selected' : '' }}>Vigente</option><option value="en_revision" {{ ($filtros['estatus_contable'] ?? '') === 'en_revision' ? 'selected' : '' }}>En revisión</option><option value="baja" {{ ($filtros['estatus_contable'] ?? '') === 'baja' ? 'selected' : '' }}>Baja</option></select></label>
          <label class="vf-field"><span>Conciliación CFDI</span><select name="conciliacion_cfdi"><option value="">Todas</option><option value="validado" {{ ($filtros['conciliacion_cfdi'] ?? '') === 'validado' ? 'selected' : '' }}>Validado</option><option value="observado" {{ ($filtros['conciliacion_cfdi'] ?? '') === 'observado' ? 'selected' : '' }}>Observado</option><option value="sin_xml" {{ ($filtros['conciliacion_cfdi'] ?? '') === 'sin_xml' ? 'selected' : '' }}>Sin XML validado</option></select></label>
          <label class="vf-field"><span>Moneda</span><input name="moneda" maxlength="3" value="{{ $filtros['moneda'] ?? '' }}"></label>
          <label class="vf-field"><span>Fecha desde</span><input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}"></label>
          <label class="vf-field"><span>Fecha hasta</span><input type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}"></label>
          <label class="vf-field"><span>Valor desde</span><input type="number" step="0.01" name="valor_desde" value="{{ $filtros['valor_desde'] ?? '' }}"></label>
          <label class="vf-field"><span>Valor hasta</span><input type="number" step="0.01" name="valor_hasta" value="{{ $filtros['valor_hasta'] ?? '' }}"></label>
          <label class="vf-field"><span>Registros</span><select name="per_page">@foreach([10,25,50,100] as $size)<option value="{{ $size }}" {{ (string)($filtros['per_page'] ?? 10) === (string)$size ? 'selected' : '' }}>{{ $size }}</option>@endforeach</select></label>
        </div>
        <div class="vf-actions"><button class="tab" type="submit">Consultar</button><button class="tab" type="submit" name="export" value="csv">Exportar CSV</button><a class="tab" href="{{ route('valores') }}">Limpiar filtros</a></div>
      </form>
    </div>
  </section>

  <section class="vf-card">
    <div class="vf-title"><h2>Valores registrados</h2><span class="pill ok">{{ $resultados->total() }} resultado(s)</span></div>
    <div class="vf-table-wrap">
      <table>
        <thead><tr><th>Activo / factura</th><th>Proveedor / planta</th><th>Valores</th><th>Moneda</th><th>Contable</th><th>Conciliación CFDI</th><th>Fecha</th><th>Acciones</th></tr></thead>
        <tbody>
          @forelse($resultados as $row)
            @php
              $conciliation = $row->conciliacion_cfdi ?: 'sin_xml';
              $conciliationClass = $conciliation === 'validado' ? 'ok' : ($conciliation === 'observado' ? 'warn' : 'danger');
              $accountClass = $row->estatus_contable === 'vigente' ? 'ok' : ($row->estatus_contable === 'en_revision' ? 'warn' : 'danger');
              $detail = is_string($row->conciliacion_detalle) ? json_decode($row->conciliacion_detalle, true) : $row->conciliacion_detalle;
              $detail = is_array($detail) ? $detail : [];
            @endphp
            <tr>
              <td><strong>{{ $row->numero_activo }}</strong><br><small>{{ $row->activo_descripcion }}</small><br><small>{{ $row->folio_factura ?: 'Sin folio' }}</small></td>
              <td>{{ $row->proveedor_nombre ?: 'Sin proveedor' }}<br><small>{{ $row->planta_nombre ?: 'Sin planta' }} · {{ $row->centro_costo_clave ?: 'Sin CC' }}</small></td>
              <td>Fiscal: ${{ number_format((float)$row->valor_fiscal,2) }}<br>Financiero: ${{ number_format((float)$row->valor_financiero,2) }}<br><small>Libros: ${{ number_format((float)$row->valor_en_libros,2) }}</small></td>
              <td>{{ $row->moneda ?: 'MXN' }}<br><small>TC: {{ $row->tipo_cambio ? number_format((float)$row->tipo_cambio,6) : 'N/A' }}</small></td>
              <td><span class="vf-status {{ $accountClass }}">{{ ucfirst(str_replace('_',' ',$row->estatus_contable)) }}</span></td>
              <td><span class="vf-status {{ $conciliationClass }}">{{ ucfirst(str_replace('_',' ',$conciliation)) }}</span><div class="vf-details">{{ implode(' ', array_slice($detail,0,2)) }}</div></td>
              <td>{{ $row->fecha_corte }}<br><small>{{ $row->updated_at }}</small></td>
              <td><div class="table-actions">
                @if($row->expediente_id)<a href="{{ route('expediente',$row->expediente_id) }}">Consultar</a>@endif
                @if($canAdministrarValores)
                  <a href="{{ route('valores', array_merge(request()->query(), ['editar_valor'=>$row->valor_id])) }}">Editar</a>
                  <form method="POST" action="{{ route('valores.destroy',$row->valor_id) }}" onsubmit="return confirm('¿Eliminar los valores del activo? El Dashboard lo marcará como pendiente.');" style="display:inline">@csrf @method('DELETE')<button type="submit" style="border:0;background:none;color:#b42318;font-weight:900;cursor:pointer;padding:0">Eliminar</button></form>
                @endif
              </div></td>
            </tr>
          @empty
            <tr><td colspan="8">No existen valores con los filtros seleccionados.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="table-footer">
      <div>Mostrando {{ $resultados->firstItem() ?? 0 }}–{{ $resultados->lastItem() ?? 0 }} de {{ $resultados->total() }}</div>
      <div class="table-pagination">@if($resultados->onFirstPage())<span class="page-link disabled">Anterior</span>@else<a class="page-link" href="{{ $resultados->previousPageUrl() }}">Anterior</a>@endif <span class="page-link active">{{ $resultados->currentPage() }}</span> @if($resultados->hasMorePages())<a class="page-link" href="{{ $resultados->nextPageUrl() }}">Siguiente</a>@else<span class="page-link disabled">Siguiente</span>@endif</div>
      <div>Consulta controlada por rol</div>
    </div>
  </section>
</div>
@endsection
