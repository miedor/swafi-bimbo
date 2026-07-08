@extends('layouts.app')

@section('title', 'Búsqueda avanzada | SWAFI')
@section('page_title', 'Búsqueda avanzada')
@section('page_subtitle', 'Localización por múltiples criterios del expediente y del activo')
@section('breadcrumb', 'Búsqueda avanzada')

@section('content')

@php
  $swafiRoles = session('swafi_roles', []);
  $swafiPermissions = session('swafi_permissions', []);

  $isAdminSwafi = in_array('Administrador SWAFI', $swafiRoles, true);
  $canEditExpedientes = $isAdminSwafi || in_array('expedientes.editar', $swafiPermissions, true);
@endphp

@if (session('success'))
  <div style="margin-bottom:14px;padding:12px 14px;border-radius:14px;background:#e8f7ea;border:1px solid #b9e5bf;color:#1f6b2a;font-weight:800;">
    {{ session('success') }}
  </div>
@endif

@if ($errors->any())
  <div style="margin-bottom:14px;padding:12px 14px;border-radius:14px;background:#fff4d6;border:1px solid #facc15;color:#7a4b00;font-weight:800;">
    @foreach ($errors->all() as $error)
      <div>{{ $error }}</div>
    @endforeach
  </div>
@endif

<section class="card form-card">
  <form method="GET" action="{{ route('busqueda') }}">
    <div class="section-title">
      <h2>Búsqueda avanzada</h2>
      <div class="tabs">
        <button class="tab" type="submit">Consultar</button>
        <a class="tab" href="{{ route('busqueda') }}">Limpiar filtros</a>
        <button class="tab" type="submit" name="export" value="csv">Exportar CSV</button>
      </div>
    </div>

    <div class="query-grid query-grid-four">
      <label>
        <span>Folio factura</span>
        <input name="folio_factura" value="{{ $filtros['folio_factura'] ?? '' }}" placeholder="Ej. FAC-000184">
      </label>

      <label>
        <span>Proveedor</span>
        <input name="proveedor" value="{{ $filtros['proveedor'] ?? '' }}" placeholder="Nombre proveedor">
      </label>

      <label>
        <span>RFC</span>
        <input name="rfc" value="{{ $filtros['rfc'] ?? '' }}" placeholder="RFC proveedor">
      </label>

      <label>
        <span>Número de activo</span>
        <input name="numero_activo" value="{{ $filtros['numero_activo'] ?? '' }}" placeholder="Ej. BIM-000001">
      </label>
    </div>

    <div class="query-grid query-grid-four">
      <label>
        <span>Planta</span>
        <select name="planta_id">
          <option value="">Todas</option>
          @foreach($catalogos['plantas'] as $planta)
            <option value="{{ $planta->id }}" {{ (($filtros['planta_id'] ?? '') == $planta->id) ? 'selected' : '' }}>
              {{ $planta->nombre }}
            </option>
          @endforeach
        </select>
      </label>

      <label>
        <span>Centro de costo</span>
        <select name="centro_costo_id">
          <option value="">Todos</option>
          @foreach($catalogos['centrosCosto'] as $centro)
            <option value="{{ $centro->id }}" {{ (($filtros['centro_costo_id'] ?? '') == $centro->id) ? 'selected' : '' }}>
              {{ $centro->clave }} - {{ $centro->descripcion }}
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
    </div>

    <div class="query-grid query-grid-four">
      <label>
        <span>Monto desde</span>
        <input type="number" step="0.01" name="monto_desde" value="{{ $filtros['monto_desde'] ?? '' }}">
      </label>

      <label>
        <span>Monto hasta</span>
        <input type="number" step="0.01" name="monto_hasta" value="{{ $filtros['monto_hasta'] ?? '' }}">
      </label>

      <label>
        <span>Estatus documental</span>
        <select name="estatus">
          <option value="">Todos</option>
          <option value="completo" {{ (($filtros['estatus'] ?? '') === 'completo') ? 'selected' : '' }}>Completo</option>
          <option value="incompleto" {{ (($filtros['estatus'] ?? '') === 'incompleto') ? 'selected' : '' }}>Incompleto</option>
          <option value="observado" {{ (($filtros['estatus'] ?? '') === 'observado') ? 'selected' : '' }}>Observado</option>
        </select>
      </label>

      <label>
        <span>Registros por página</span>
        <select name="per_page">
          @foreach([10, 25, 50] as $size)
            <option value="{{ $size }}" {{ (($filtros['per_page'] ?? 10) == $size) ? 'selected' : '' }}>{{ $size }}</option>
          @endforeach
        </select>
      </label>
    </div>

    <div class="action-group">
      <button class="tab" type="submit">Consultar</button>
      <a class="tab" href="{{ route('busqueda') }}">Limpiar filtros</a>
      <button class="tab" type="submit" name="export" value="csv">Exportar consulta</button>
    </div>
  </form>
</section>

<section class="card table-card" style="margin-top:20px">
  <div class="section-title">
    <h2>Resultados de consulta</h2>
    <span class="pill ok">Consulta dinámica con paginación</span>
  </div>

  <table>
    <thead>
      <tr>
        <th>Folio</th>
        <th>Activo</th>
        <th>Proveedor</th>
        <th>Planta</th>
        <th>Fecha</th>
        <th>Monto</th>
        <th>Estatus</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse($resultados as $row)
        <tr>
          <td>{{ $row->folio_factura }}</td>
          <td>
            <strong>{{ $row->numero_activo }}</strong><br>
            <small>{{ $row->activo_descripcion }}</small>
          </td>
          <td>
            {{ $row->proveedor_nombre ?? 'Sin proveedor' }}<br>
            <small>{{ $row->proveedor_rfc }}</small>
          </td>
          <td>{{ $row->planta_nombre ?? 'Sin planta' }}</td>
          <td>{{ $row->fecha_factura }}</td>
          <td>$ {{ number_format((float) $row->monto_factura, 2) }} {{ $row->moneda }}</td>
          <td>
            @php
              $pillClass = 'danger';

              if ($row->estatus === 'completo') {
                  $pillClass = 'ok';
              } elseif ($row->estatus === 'observado') {
                  $pillClass = 'warn';
              }
            @endphp
            <span class="pill {{ $pillClass }}">{{ ucfirst($row->estatus) }}</span>
          </td>
          <td>
            <div class="table-actions">
              <a href="{{ route('expediente', $row->expediente_id) }}">Consultar</a>

              @if($canEditExpedientes)
                <a href="{{ route('expedientes.editar', $row->expediente_id) }}">Editar</a>

                <form
                  method="POST"
                  action="{{ route('expedientes.eliminar', $row->expediente_id) }}"
                  style="display:inline"
                  onsubmit="return confirm('¿Deseas eliminar este expediente? El activo fijo permanecerá registrado y la acción se guardará en bitácora.');"
                >
                  @csrf
                  @method('DELETE')

                  <button
                    type="submit"
                    style="border:0;background:none;color:#b42318;font-weight:800;cursor:pointer;padding:0"
                  >
                    Eliminar
                  </button>
                </form>
              @endif
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="8">
            No se encontraron expedientes con los criterios seleccionados.
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
      @if($resultados->onFirstPage())
        <span class="page-link disabled">Anterior</span>
      @else
        <a class="page-link" href="{{ $resultados->previousPageUrl() }}">Anterior</a>
      @endif

      <span class="page-link active">{{ $resultados->currentPage() }}</span>

      @if($resultados->hasMorePages())
        <a class="page-link" href="{{ $resultados->nextPageUrl() }}">Siguiente</a>
      @else
        <span class="page-link disabled">Siguiente</span>
      @endif
    </div>

    <div class="table-page-size">
      <span>Consulta conectada a MySQL</span>
    </div>
  </div>
</section>

@endsection
