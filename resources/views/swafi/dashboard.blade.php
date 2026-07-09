@extends('layouts.app')

@section('title', 'Dashboard principal | SWAFI')
@section('page_title', 'Dashboard principal')
@section('page_subtitle', 'Resumen dinámico del control documental, patrimonial y operativo')
@section('breadcrumb', 'Dashboard')

@section('page_styles')
<style>
  .dash-filter {
    margin-bottom: 18px;
    padding: 14px;
    border: 1px solid #e1eaf6;
    border-radius: 18px;
    background: #f8fbff;
  }

  .dash-filter label {
    display: block;
  }

  .dash-filter span {
    display: block;
    margin-bottom: 5px;
    color: #1d3558;
    font-size: 12px;
    font-weight: 900;
  }

  .dash-filter input,
  .dash-filter select {
    width: 100%;
    min-height: 38px;
    padding: 8px 10px;
    border: 1px solid #d5e1ef;
    border-radius: 11px;
    background: #ffffff;
    color: #16304d;
    font-size: 13px;
  }

  .dash-kpi-sub {
    margin-top: 5px;
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
  }

  .dash-grid-two {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
    align-items: start;
  }

  .dash-progress-list {
    display: grid;
    gap: 12px;
  }

  .dash-progress-item {
    display: grid;
    gap: 6px;
  }

  .dash-progress-title {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    color: #183458;
    font-size: 13px;
    font-weight: 900;
  }

  .dash-progress-track {
    height: 13px;
    overflow: hidden;
    border-radius: 999px;
    background: #e8eef7;
  }

  .dash-progress-bar {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #1f5aa6, #4f8fe8);
  }

  .dash-progress-bar.warn {
    background: linear-gradient(90deg, #d97706, #fbbf24);
  }

  .dash-progress-bar.danger {
    background: linear-gradient(90deg, #b42318, #ef4444);
  }

  .dash-progress-bar.ok {
    background: linear-gradient(90deg, #1f7a3d, #22c55e);
  }

  .dash-table-scroll {
    width: 100%;
    overflow-x: auto;
  }

  .dash-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
  }

  .dash-mini {
    color: #64748b;
    font-size: 12px;
    line-height: 1.35;
  }

  .dash-empty {
    padding: 18px;
    border: 1px dashed #cbd8ea;
    border-radius: 14px;
    background: #f8fbff;
    color: #64748b;
    font-weight: 800;
  }

  @media (max-width: 1100px) {
    .dash-grid-two {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 760px) {
    .query-grid-four {
      grid-template-columns: 1fr !important;
    }
  }
</style>
@endsection

@section('content')

@php
    $roles = session('swafi_roles', []);
    $permissions = session('swafi_permissions', []);

    $can = function (string $permission) use ($roles, $permissions): bool {
        if (in_array('Administrador SWAFI', $roles, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    };

    $estatusClass = function (?string $estatus): string {
        return match ($estatus) {
            'completo' => 'ok',
            'observado' => 'warn',
            'incompleto' => 'danger',
            default => 'warn',
        };
    };

    $statusText = function (?string $estatus): string {
        $estatus = (string) $estatus;

        return $estatus !== ''
            ? ucfirst($estatus)
            : 'Sin estatus';
    };

    $totalEstatus = max((int) ($kpis['total_expedientes'] ?? 0), 1);
    $filtros = $filtros ?? [];
@endphp

@if ($errors->any())
    <div style="margin-bottom:14px;padding:12px 14px;border-radius:14px;background:#fff4d6;border:1px solid #facc15;color:#7a4b00;font-weight:800;">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<section class="card">
  <div class="section-title">
    <h2>Filtros del tablero</h2>
    <span class="pill ok">Conectado a MySQL</span>
  </div>

  <form method="GET" action="{{ route('dashboard') }}" class="dash-filter">
    <div class="query-grid query-grid-four">
      <label>
        <span>Planta</span>
        <select name="planta_id">
          <option value="">Todas</option>
          @foreach($catalogos['plantas'] as $planta)
            @php
              $plantaSeleccionada = (string) ($filtros['planta_id'] ?? '');
            @endphp
            <option value="{{ $planta->id }}" {{ $plantaSeleccionada === (string) $planta->id ? 'selected' : '' }}>
              {{ $planta->nombre }}
            </option>
          @endforeach
        </select>
      </label>

      <label>
        <span>Fecha factura desde</span>
        <input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
      </label>

      <label>
        <span>Fecha factura hasta</span>
        <input type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
      </label>

      <label>
        <span>Acciones</span>
        <div class="dash-actions">
          <button class="tab" type="submit">Actualizar tablero</button>
          <a class="tab" href="{{ route('dashboard') }}">Limpiar</a>
        </div>
      </label>
    </div>
  </form>
</section>

<div class="grid-kpi" style="margin-top:18px">
  <div class="card kpi">
    <div class="label">Activos registrados</div>
    <div class="value">{{ number_format((int) $kpis['total_activos']) }}</div>
    <div class="status">Activos vigentes en SWAFI</div>
  </div>

  <div class="card kpi">
    <div class="label">Expedientes registrados</div>
    <div class="value">{{ number_format((int) $kpis['total_expedientes']) }}</div>
    <div class="status">Facturas/expedientes documentales</div>
  </div>

  <div class="card kpi">
    <div class="label">Expedientes completos</div>
    <div class="value">{{ number_format((int) $kpis['expedientes_completos']) }}</div>
    <div class="status">{{ number_format((float) $kpis['porcentaje_completos'], 1) }}% del total filtrado</div>
  </div>

  <div class="card kpi">
    <div class="label">Pendientes documentales</div>
    <div class="value">{{ number_format((int) $kpis['expedientes_incompletos']) }}</div>
    <div class="status">{{ number_format((float) $kpis['porcentaje_incompletos'], 1) }}% incompletos/observados</div>
  </div>

  <div class="card kpi">
    <div class="label">Activos sin ubicación</div>
    <div class="value">{{ number_format((int) $kpis['activos_sin_ubicacion']) }}</div>
    <div class="status">Requieren validación física</div>
  </div>
</div>

<div class="grid-kpi" style="margin-top:18px">
  <div class="card kpi">
    <div class="label">Activos sin valores</div>
    <div class="value">{{ number_format((int) $kpis['activos_sin_valores']) }}</div>
    <div class="status">Sin valores fiscales/financieros</div>
  </div>

  <div class="card kpi">
    <div class="label">Monto registrado</div>
    <div class="value">$ {{ number_format((float) $kpis['monto_total'], 2) }}</div>
    <div class="status">Suma de facturas del filtro</div>
  </div>

  <div class="card kpi">
    <div class="label">Documentos PDF vigentes</div>
    <div class="value">{{ number_format((int) $kpis['documentos_pdf']) }}</div>
    <div class="status">Resguardados en expediente</div>
  </div>

  <div class="card kpi">
    <div class="label">Documentos XML vigentes</div>
    <div class="value">{{ number_format((int) $kpis['documentos_xml']) }}</div>
    <div class="status">Soporte CFDI asociado</div>
  </div>

  <div class="card kpi">
    <div class="label">Eventos de auditoría</div>
    <div class="value">{{ number_format((int) $kpis['eventos_auditoria']) }}</div>
    <div class="status">Trazabilidad registrada</div>
  </div>
</div>

<div class="dash-grid-two" style="margin-top:20px">
  <section class="card">
    <div class="section-title">
      <h2>Estado documental</h2>

      @if ($can('reportes.exportar'))
        <a class="btn btn-secondary" href="{{ route('reportes', array_filter([
          'tipo_reporte' => 'expedientes_documentales',
          'planta_id' => $filtros['planta_id'] ?? null,
          'fecha_desde' => $filtros['fecha_desde'] ?? null,
          'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
        ])) }}">Ver reportes</a>
      @endif
    </div>

    <div class="dash-progress-list">
      @forelse($estatusDocumental as $estatus)
        @php
          $porcentaje = round(((int) $estatus->total / $totalEstatus) * 100, 1);
          $barClass = $estatusClass($estatus->estatus);
        @endphp

        <div class="dash-progress-item">
          <div class="dash-progress-title">
            <span>{{ $statusText($estatus->estatus) }}</span>
            <span>{{ number_format((int) $estatus->total) }} · {{ number_format($porcentaje, 1) }}%</span>
          </div>
          <div class="dash-progress-track">
            <div class="dash-progress-bar {{ $barClass }}" style="width: {{ max($porcentaje, 2) }}%"></div>
          </div>
        </div>
      @empty
        <div class="dash-empty">
          No existen expedientes para graficar con los filtros seleccionados.
        </div>
      @endforelse
    </div>

    <div class="footer-note" style="margin-top:12px">
      El tablero se alimenta directamente de la tabla de expedientes y considera los filtros aplicados.
    </div>
  </section>

  <section class="card">
    <div class="section-title">
      <h2>Activos por planta</h2>
      <span class="pill ok">Distribución operativa</span>
    </div>

    <div class="dash-progress-list">
      @php
        $maxPlanta = max((int) ($activosPorPlanta->max('total') ?? 0), 1);
      @endphp

      @forelse($activosPorPlanta as $planta)
        @php
          $porcentajePlanta = round(((int) $planta->total / $maxPlanta) * 100, 1);
        @endphp

        <div class="dash-progress-item">
          <div class="dash-progress-title">
            <span>{{ $planta->planta_nombre }}</span>
            <span>{{ number_format((int) $planta->total) }} activo(s)</span>
          </div>
          <div class="dash-progress-track">
            <div class="dash-progress-bar ok" style="width: {{ max($porcentajePlanta, 2) }}%"></div>
          </div>
        </div>
      @empty
        <div class="dash-empty">
          No hay activos registrados por planta.
        </div>
      @endforelse
    </div>
  </section>
</div>

<section class="card" style="margin-top:20px">
  <div class="section-title">
    <h2>Expedientes que requieren atención</h2>
    <span class="pill warn">Seguimiento documental</span>
  </div>

  <div class="dash-table-scroll">
    <table>
      <thead>
        <tr>
          <th>Activo</th>
          <th>Factura</th>
          <th>Proveedor / Planta</th>
          <th>Estatus</th>
          <th>PDF</th>
          <th>XML</th>
          <th>Ubicación</th>
          <th>Acciones</th>
        </tr>
      </thead>

      <tbody>
        @forelse($expedientesAtencion as $item)
          <tr>
            <td>
              <strong>{{ $item->numero_activo }}</strong><br>
              <span class="dash-mini">{{ $item->activo_descripcion }}</span>
            </td>

            <td>{{ $item->folio_factura }}</td>

            <td>
              {{ $item->proveedor_nombre ?: 'Sin proveedor' }}<br>
              <span class="dash-mini">{{ $item->planta_nombre ?: 'Sin planta' }}</span>
            </td>

            <td>
              <span class="pill {{ $estatusClass($item->estatus) }}">
                {{ $statusText($item->estatus) }}
              </span>
            </td>

            <td>{{ ((int) $item->total_pdf) > 0 ? 'Sí' : 'No' }}</td>
            <td>{{ ((int) $item->total_xml) > 0 ? 'Sí' : 'No' }}</td>
            <td>{{ $item->ubicacion_id ? 'Asignada' : 'Pendiente' }}</td>

            <td>
              <div class="table-actions">
                @if($can('expedientes.ver'))
                  <a href="{{ route('expediente', $item->expediente_id) }}">Consultar</a>
                @endif

                @if($can('expedientes.editar'))
                  <a href="{{ route('expedientes.editar', $item->expediente_id) }}">Editar</a>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8">
              No hay expedientes pendientes con los filtros seleccionados.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

<div class="dash-grid-two" style="margin-top:20px">
  <section class="card">
    <div class="section-title">
      <h2>Últimos documentos cargados</h2>
      <span class="pill ok">PDF/XML vigentes</span>
    </div>

    <div class="list">
      @forelse($ultimosDocumentos as $documento)
        <div class="list-item">
          <strong>
            {{ $documento->tipo_documento }} · {{ $documento->nombre_archivo }}
          </strong>
          <span>
            {{ $documento->numero_activo }} · {{ $documento->folio_factura }} · v{{ $documento->version }}
          </span>
        </div>
      @empty
        <div class="dash-empty">
          Aún no existen documentos vigentes cargados.
        </div>
      @endforelse
    </div>
  </section>

  <section class="card">
    <div class="section-title">
      <h2>Actividad reciente</h2>

      @if ($can('bitacora.ver'))
        <a class="pill ok" href="{{ route('seguridad', ['tab' => 'bitacora']) }}">Ver bitácora</a>
      @else
        <span class="pill ok">Trazabilidad</span>
      @endif
    </div>

    <div class="list">
      @forelse($actividadReciente as $evento)
        <div class="list-item">
          <strong>
            {{ $evento->accion }} · {{ $evento->modulo }}
          </strong>
          <span>
            {{ $evento->fecha_evento }}
            · {{ $evento->usuario_nombre ?: ($evento->usuario_email ?: 'Usuario no identificado') }}
            @if($evento->numero_activo)
              · {{ $evento->numero_activo }}
            @endif
          </span>
        </div>
      @empty
        <div class="dash-empty">
          Aún no hay actividad registrada en bitácora.
        </div>
      @endforelse
    </div>
  </section>
</div>

<section class="card" style="margin-top:20px">
  <div class="section-title">
    <h2>Accesos rápidos permitidos</h2>
    <span class="pill warn">Filtrado por rol</span>
  </div>

  <div class="quick-links">
    @if ($can('expedientes.crear'))
      <a href="{{ route('registro-individual') }}">Registro individual</a>
      <a href="{{ route('registro-masivo') }}">Registro masivo</a>
    @endif

    @if ($can('valores.administrar'))
      <a href="{{ route('valores') }}">Valores fiscales y financieros</a>
    @endif

    @if ($can('ubicaciones.administrar'))
      <a href="{{ route('ubicacion') }}">Ubicación e inventario</a>
    @endif

    @if ($can('expedientes.ver'))
      <a href="{{ route('busqueda') }}">Búsqueda avanzada</a>
      <a href="{{ route('expediente') }}">Detalle de expediente</a>
    @endif

    @if ($can('reportes.exportar'))
      <a href="{{ route('reportes') }}">Reportes ad hoc</a>
    @endif

    @if ($can('catalogos.administrar'))
      <a href="{{ route('catalogos') }}">Catálogos base</a>
    @endif

    @if ($can('seguridad.administrar'))
      <a href="{{ route('seguridad', ['tab' => 'usuarios']) }}">Seguridad y acceso</a>
    @endif

    @if ($can('bitacora.ver'))
      <a href="{{ route('seguridad', ['tab' => 'bitacora']) }}">Bitácora</a>
    @endif
  </div>
</section>

@endsection
