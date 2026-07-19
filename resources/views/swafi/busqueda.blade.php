@extends('layouts.app')

@section('title', 'Búsqueda avanzada | SWAFI')
@section('page_title', 'Búsqueda avanzada')
@section('page_subtitle', 'Consulta por criterios, ubicación, estatus y búsquedas guardadas')
@section('breadcrumb', 'Búsqueda avanzada')

@section('page_styles')
<style nonce="{{ request()->attributes->get('csp_nonce') }}">
  .search-shell {
    display: grid;
    gap: 16px;
  }

  .search-top-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    gap: 16px;
    align-items: start;
  }

  .search-form-card,
  .saved-card {
    min-width: 0;
  }

  .search-form-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
  }

  .search-form-grid + .search-form-grid {
    margin-top: 10px;
  }

  .search-field {
    display: block;
    min-width: 0;
  }

  .search-field > span {
    display: block;
    margin-bottom: 5px;
    color: #1d3558;
    font-size: 12px;
    font-weight: 900;
  }

  .search-field input,
  .search-field select {
    width: 100%;
    min-height: 39px;
    padding: 8px 10px;
    border: 1px solid #d5e1ef;
    border-radius: 11px;
    background: #ffffff;
    color: #16304d;
    font-size: 13px;
  }

  .search-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
  }

  .saved-create {
    padding: 12px;
    border: 1px dashed #b8cbe4;
    border-radius: 15px;
    background: #f8fbff;
  }

  .saved-create label {
    display: block;
  }

  .saved-create span {
    display: block;
    margin-bottom: 5px;
    color: #1d3558;
    font-size: 12px;
    font-weight: 900;
  }

  .saved-create input {
    width: 100%;
    min-height: 39px;
    padding: 8px 10px;
    border: 1px solid #d5e1ef;
    border-radius: 11px;
    background: #ffffff;
    color: #16304d;
    font-size: 13px;
  }

  .saved-list {
    display: grid;
    gap: 8px;
    max-height: 300px;
    overflow-y: auto;
    margin-top: 12px;
    padding-right: 3px;
  }

  .saved-item {
    padding: 10px;
    border: 1px solid #e0eaf7;
    border-radius: 14px;
    background: #ffffff;
  }

  .saved-item strong {
    display: block;
    color: #15355d;
    font-size: 13px;
    font-weight: 950;
  }

  .saved-item small {
    display: block;
    margin-top: 3px;
    color: #64748b;
    line-height: 1.3;
  }

  .saved-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 8px;
  }

  .saved-actions form {
    margin: 0;
  }

  .search-table-wrap {
    width: 100%;
    overflow-x: auto;
  }

  .search-table-wrap table {
    min-width: 1120px;
  }

  .location-summary {
    color: #64748b;
    font-size: 12px;
    line-height: 1.3;
  }

  .search-message {
    padding: 12px 14px;
    border-radius: 14px;
    font-size: 13px;
    font-weight: 800;
  }

  .search-message-success {
    background: #e8f7ea;
    border: 1px solid #b9e5bf;
    color: #1f6b2a;
  }

  .search-message-error {
    background: #fff4d6;
    border: 1px solid #facc15;
    color: #7a4b00;
  }

  @media (max-width: 1180px) {
    .search-top-grid {
      grid-template-columns: 1fr;
    }

    .saved-list {
      max-height: 220px;
    }
  }

  @media (max-width: 900px) {
    .search-form-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 620px) {
    .search-form-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
@endsection

@section('content')

@php
  $swafiRoles = session('swafi_roles', []);
  $swafiPermissions = session('swafi_permissions', []);

  $isAdminSwafi = in_array('Administrador SWAFI', $swafiRoles, true);
  $canEditExpedientes = $isAdminSwafi || in_array('expedientes.editar', $swafiPermissions, true);

  $estatusSeleccionado = (string) ($filtros['estatus'] ?? '');
  $estatusOperativoSeleccionado = (string) ($filtros['estatus_operativo'] ?? '');
  $ordenSeleccionado = (string) ($filtros['ordenar_por'] ?? 'fecha_factura');
  $direccionSeleccionada = (string) ($filtros['direccion'] ?? 'desc');
  $perPageSeleccionado = (string) ($filtros['per_page'] ?? '10');
  $documentaryStatusLabels = collect($catalogos['estatusDocumentales'] ?? [])->pluck('nombre', 'clave')->all();
  $operationalStatusLabels = collect($catalogos['estatusOperativos'] ?? [])->pluck('nombre', 'clave')->all();

  $descripcionFiltros = function ($busqueda): string {
      $filtrosGuardados = (array) ($busqueda->filtros ?? []);
      $labels = [];

      $campos = [
          'numero_activo' => 'Activo',
          'folio_factura' => 'Folio',
          'uuid_cfdi' => 'UUID',
          'proveedor' => 'Proveedor',
          'rfc' => 'RFC',
          'estatus' => 'Estatus documental',
          'estatus_operativo' => 'Estatus operativo',
          'fecha_desde' => 'Desde',
          'fecha_hasta' => 'Hasta',
      ];

      foreach ($campos as $campo => $etiqueta) {
          if (!empty($filtrosGuardados[$campo])) {
              $labels[] = $etiqueta . ': ' . $filtrosGuardados[$campo];
          }
      }

      if (empty($labels)) {
          return 'Filtros por catálogos, montos u ordenamiento.';
      }

      return implode(' · ', array_slice($labels, 0, 3));
  };
@endphp

<div class="search-shell" data-swafi-query-workspace data-swafi-query-key="busqueda">
  @if (session('success'))
    <div class="search-message search-message-success">
      {{ session('success') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="search-message search-message-error">
      @foreach ($errors->all() as $error)
        <div>{{ $error }}</div>
      @endforeach
    </div>
  @endif

  <div class="search-top-grid" data-swafi-query-panel>
    <section class="card form-card search-form-card">
      <form id="swafiSearchFiltersForm" method="GET" action="{{ route('busqueda') }}" data-swafi-query-form>
        <div class="section-title">
          <h2>Criterios de búsqueda</h2>
          <span class="pill ok">Filtros completos y ordenamiento</span>
        </div>

        <div class="search-form-grid">
          <label class="search-field">
            <span>Folio de factura</span>
            <input name="folio_factura" value="{{ $filtros['folio_factura'] ?? '' }}" placeholder="Ej. FAC-000184">
          </label>

          <label class="search-field">
            <span>UUID CFDI</span>
            <input name="uuid_cfdi" value="{{ $filtros['uuid_cfdi'] ?? '' }}" placeholder="UUID completo o parcial">
          </label>

          <label class="search-field">
            <span>Número de activo</span>
            <input name="numero_activo" value="{{ $filtros['numero_activo'] ?? '' }}" placeholder="Ej. BIM-537028">
          </label>

          <label class="search-field">
            <span>Proveedor</span>
            <input name="proveedor" value="{{ $filtros['proveedor'] ?? '' }}" placeholder="Nombre del proveedor">
          </label>
        </div>

        <div class="search-form-grid">
          <label class="search-field">
            <span>RFC</span>
            <input name="rfc" value="{{ $filtros['rfc'] ?? '' }}" placeholder="RFC del proveedor">
          </label>

          <label class="search-field">
            <span>Planta</span>
            <select name="planta_id">
              <option value="">Todas</option>
              @foreach($catalogos['plantas'] as $planta)
                <option value="{{ $planta->id }}" {{ (string) ($filtros['planta_id'] ?? '') === (string) $planta->id ? 'selected' : '' }}>
                  {{ $planta->nombre }}
                </option>
              @endforeach
            </select>
          </label>

          <label class="search-field">
            <span>Centro de costo</span>
            <select name="centro_costo_id">
              <option value="">Todos</option>
              @foreach($catalogos['centrosCosto'] as $centro)
                <option value="{{ $centro->id }}" {{ (string) ($filtros['centro_costo_id'] ?? '') === (string) $centro->id ? 'selected' : '' }}>
                  {{ $centro->clave }} - {{ $centro->descripcion }}
                </option>
              @endforeach
            </select>
          </label>

          <label class="search-field">
            <span>Área</span>
            <select name="area_id">
              <option value="">Todas</option>
              @foreach($catalogos['areas'] as $area)
                <option value="{{ $area->id }}" {{ (string) ($filtros['area_id'] ?? '') === (string) $area->id ? 'selected' : '' }}>
                  {{ $area->planta_nombre ? $area->planta_nombre . ' · ' : '' }}{{ $area->nombre }}
                </option>
              @endforeach
            </select>
          </label>
        </div>

        <div class="search-form-grid">
          <label class="search-field">
            <span>Ubicación física</span>
            <select name="ubicacion_id">
              <option value="">Todas</option>
              @foreach($catalogos['ubicaciones'] as $ubicacion)
                <option value="{{ $ubicacion->id }}" {{ (string) ($filtros['ubicacion_id'] ?? '') === (string) $ubicacion->id ? 'selected' : '' }}>
                  {{ $ubicacion->planta_nombre ? $ubicacion->planta_nombre . ' · ' : '' }}
                  {{ $ubicacion->area_nombre ? $ubicacion->area_nombre . ' · ' : '' }}
                  {{ $ubicacion->codigo_interno ?: $ubicacion->descripcion }}
                </option>
              @endforeach
            </select>
          </label>

          <label class="search-field">
            <span>Estatus documental</span>
            <select name="estatus">
              <option value="">Todos</option>
              @foreach($catalogos['estatusDocumentales'] as $estatusDocumental)
                <option value="{{ $estatusDocumental->clave }}" {{ $estatusSeleccionado === $estatusDocumental->clave ? 'selected' : '' }}>
                  {{ $estatusDocumental->nombre }}
                </option>
              @endforeach
            </select>
          </label>

          <label class="search-field">
            <span>Estatus operativo</span>
            <select name="estatus_operativo">
              <option value="">Todos</option>
              @foreach($catalogos['estatusOperativos'] as $estatusOperativo)
                <option value="{{ $estatusOperativo->clave }}" {{ $estatusOperativoSeleccionado === $estatusOperativo->clave ? 'selected' : '' }}>
                  {{ $estatusOperativo->nombre }}
                </option>
              @endforeach
            </select>
          </label>

          <label class="search-field">
            <span>Registros por página</span>
            <select name="per_page">
              @foreach([10, 25, 50, 100] as $size)
                <option value="{{ $size }}" {{ $perPageSeleccionado === (string) $size ? 'selected' : '' }}>{{ $size }}</option>
              @endforeach
            </select>
          </label>
        </div>

        <div class="search-form-grid">
          <label class="search-field">
            <span>Fecha desde</span>
            <input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
          </label>

          <label class="search-field">
            <span>Fecha hasta</span>
            <input type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
          </label>

          <label class="search-field">
            <span>Monto desde</span>
            <input type="number" step="0.01" min="0" name="monto_desde" value="{{ $filtros['monto_desde'] ?? '' }}">
          </label>

          <label class="search-field">
            <span>Monto hasta</span>
            <input type="number" step="0.01" min="0" name="monto_hasta" value="{{ $filtros['monto_hasta'] ?? '' }}">
          </label>
        </div>

        <div class="search-form-grid">
          <label class="search-field">
            <span>Ordenar por</span>
            <select name="ordenar_por">
              <option value="fecha_factura" {{ $ordenSeleccionado === 'fecha_factura' ? 'selected' : '' }}>Fecha de factura</option>
              <option value="fecha_registro" {{ $ordenSeleccionado === 'fecha_registro' ? 'selected' : '' }}>Fecha de registro</option>
              <option value="numero_activo" {{ $ordenSeleccionado === 'numero_activo' ? 'selected' : '' }}>Número de activo</option>
              <option value="folio_factura" {{ $ordenSeleccionado === 'folio_factura' ? 'selected' : '' }}>Folio de factura</option>
              <option value="proveedor" {{ $ordenSeleccionado === 'proveedor' ? 'selected' : '' }}>Proveedor</option>
              <option value="planta" {{ $ordenSeleccionado === 'planta' ? 'selected' : '' }}>Planta</option>
              <option value="monto_factura" {{ $ordenSeleccionado === 'monto_factura' ? 'selected' : '' }}>Monto</option>
              <option value="estatus" {{ $ordenSeleccionado === 'estatus' ? 'selected' : '' }}>Estatus documental</option>
            </select>
          </label>

          <label class="search-field">
            <span>Dirección</span>
            <select name="direccion">
              <option value="desc" {{ $direccionSeleccionada === 'desc' ? 'selected' : '' }}>Descendente</option>
              <option value="asc" {{ $direccionSeleccionada === 'asc' ? 'selected' : '' }}>Ascendente</option>
            </select>
          </label>
        </div>

        <div class="search-actions">
          <button class="tab" type="submit">Consultar</button>

          @if($canExportReports)
            <button class="tab" type="submit" name="export" value="csv">Exportar CSV</button>
            <button class="tab" type="submit" name="export" value="xlsx">Exportar Excel</button>
            <button class="tab" type="submit" name="export" value="pdf">Exportar PDF</button>
          @endif

          <a class="tab" href="{{ route('busqueda') }}">Limpiar filtros</a>
        </div>
      </form>
    </section>

    <aside class="card saved-card">
      <div class="section-title">
        <h2>Búsquedas guardadas</h2>
        <span class="pill ok">Personalizadas</span>
      </div>

      <form id="swafiSaveSearchForm" method="POST" action="{{ route('busquedas-guardadas.store') }}" class="saved-create">
        @csrf

        <label>
          <span>Nombre de la búsqueda actual</span>
          <input name="nombre" value="{{ old('nombre') }}" maxlength="100" placeholder="Ej. Expedientes observados Santa María" required>
        </label>

        @foreach($camposGuardables as $campoGuardable)
          <input
            type="hidden"
            name="filtros[{{ $campoGuardable }}]"
            value="{{ old('filtros.' . $campoGuardable, $filtros[$campoGuardable] ?? '') }}"
            data-swafi-saved-filter="{{ $campoGuardable }}"
          >
        @endforeach

        <div class="search-actions">
          <button class="tab" type="submit">Guardar búsqueda</button>
        </div>
      </form>

      <div class="saved-list">
        @forelse($busquedasGuardadas as $busquedaGuardada)
          <div class="saved-item">
            <strong>{{ $busquedaGuardada->nombre }}</strong>
            <small>{{ $descripcionFiltros($busquedaGuardada) }}</small>
            <small>Actualizada: {{ optional($busquedaGuardada->updated_at)->format('d/m/Y H:i') }}</small>

            <div class="saved-actions">
              <a class="tab" href="{{ route('busquedas-guardadas.apply', $busquedaGuardada->id) }}">Aplicar</a>

              <form
                method="POST"
                action="{{ route('busquedas-guardadas.destroy', $busquedaGuardada->id) }}"
                data-confirm="¿Deseas dar de baja lógicamente esta búsqueda guardada? La configuración se conservará para trazabilidad."
              >
                @csrf
                @method('DELETE')
                <input type="hidden" name="motivo_baja" value="Baja lógica solicitada por la persona propietaria de la búsqueda.">
                <button class="tab" type="submit">Dar de baja</button>
              </form>
            </div>
          </div>
        @empty
          <div class="saved-item">
            <strong>Aún no hay búsquedas guardadas</strong>
            <small>Configura filtros y asigna un nombre para reutilizarlos posteriormente.</small>
          </div>
        @endforelse
      </div>
    </aside>
  </div>

  <section class="card table-card" data-swafi-query-results id="swafi-busqueda-resultados">
    <div class="section-title">
      <h2>Resultados de consulta</h2>
      <span class="pill ok">{{ $resultados->total() }} resultado(s)</span>
    </div>

    <div class="search-table-wrap">
      <table>
        <thead>
          <tr>
            <th>Folio / UUID</th>
            <th>Activo</th>
            <th>Proveedor</th>
            <th>Planta / ubicación</th>
            <th>Fecha</th>
            <th>Monto</th>
            <th>Estatus</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($resultados as $row)
            <tr>
              <td>
                <strong>{{ $row->folio_factura }}</strong><br>
                <small>{{ $row->uuid_cfdi ?: 'Sin UUID' }}</small>
              </td>

              <td>
                <strong>{{ $row->numero_activo }}</strong><br>
                <small>{{ $row->activo_descripcion }}</small>
              </td>

              <td>
                {{ $row->proveedor_nombre ?? 'Sin proveedor' }}<br>
                <small>{{ $row->proveedor_rfc ?: 'Sin RFC' }}</small>
              </td>

              <td>
                {{ $row->planta_nombre ?? 'Sin planta' }}<br>
                <span class="location-summary">
                  {{ $row->area_nombre ?: 'Sin área' }} ·
                  {{ $row->ubicacion_codigo ?: ($row->ubicacion_descripcion ?: 'Sin ubicación') }}
                </span>
              </td>

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

                  $documentalTexto = $documentaryStatusLabels[$row->estatus]
                      ?? \Illuminate\Support\Str::headline((string) $row->estatus);
                  $operativoTexto = $operationalStatusLabels[$row->estatus_operativo]
                      ?? \Illuminate\Support\Str::headline((string) $row->estatus_operativo);
                @endphp

                <span class="pill {{ $pillClass }}">{{ $documentalTexto }}</span><br>
                <small>{{ $operativoTexto }}</small>
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
                      data-confirm="¿Deseas dar de baja lógicamente este expediente? El activo, los documentos y la trazabilidad permanecerán almacenados."
                    >
                      @csrf
                      @method('DELETE')
                      <input type="hidden" name="motivo_baja" value="Baja lógica solicitada desde la búsqueda avanzada.">

                      <button
                        type="submit"
                        style="border:0;background:none;color:#b42318;font-weight:800;cursor:pointer;padding:0"
                      >
                        Dar de baja
                      </button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8">No se encontraron expedientes con los criterios seleccionados.</td>
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
        <span>Consulta avanzada con trazabilidad</span>
      </div>
    </div>
  </section>
</div>

@endsection

@section('page_scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
  document.addEventListener('DOMContentLoaded', function () {
    const filtersForm = document.getElementById('swafiSearchFiltersForm');
    const savedSearchForm = document.getElementById('swafiSaveSearchForm');

    if (!filtersForm || !savedSearchForm) {
      return;
    }

    const synchronizeSavedSearchFilters = function () {
      const currentFilters = new FormData(filtersForm);

      savedSearchForm
        .querySelectorAll('[data-swafi-saved-filter]')
        .forEach(function (hiddenInput) {
          const fieldName = hiddenInput.dataset.swafiSavedFilter;
          const currentValue = currentFilters.get(fieldName);

          hiddenInput.value = currentValue === null ? '' : String(currentValue);
        });
    };

    filtersForm.addEventListener('input', synchronizeSavedSearchFilters);
    filtersForm.addEventListener('change', synchronizeSavedSearchFilters);
    savedSearchForm.addEventListener('submit', synchronizeSavedSearchFilters);

    synchronizeSavedSearchFilters();
  });
</script>
@endsection
