@extends('layouts.app')

@section('title', 'Detalle de expediente | SWAFI')
@section('page_title', 'Detalle de expediente')
@section('page_subtitle', 'Vista integral del expediente documental, patrimonial y seguimiento')
@section('breadcrumb', 'Detalle de expediente')

@section('page_styles')
<style>
  .obs-grid {
    display: grid;
    grid-template-columns: .9fr 1.1fr;
    gap: 16px;
    align-items: start;
  }

  .obs-form {
    padding: 14px;
    border: 1px dashed #b8cbe4;
    border-radius: 16px;
    background: #f8fbff;
  }

  .obs-form label {
    display: block;
    margin-bottom: 10px;
  }

  .obs-form span {
    display: block;
    margin-bottom: 6px;
    color: #1d3558;
    font-size: 12px;
    font-weight: 900;
  }

  .obs-form input,
  .obs-form select,
  .obs-form textarea {
    width: 100%;
    min-height: 38px;
    padding: 8px 10px;
    border: 1px solid #d5e1ef;
    border-radius: 11px;
    background: #fff;
    color: #16304d;
    font-size: 13px;
    box-sizing: border-box;
  }

  .obs-form textarea {
    min-height: 86px;
    resize: vertical;
  }

  .obs-list {
    display: grid;
    gap: 10px;
  }

  .obs-card {
    padding: 13px;
    border: 1px solid #e1eaf6;
    border-radius: 16px;
    background: #fff;
  }

  .obs-card-head {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: flex-start;
    margin-bottom: 8px;
  }

  .obs-card-head strong {
    display: block;
    color: #14355f;
    font-size: 14px;
    font-weight: 950;
  }

  .obs-card p {
    margin: 6px 0;
    color: #334155;
    font-size: 13px;
    line-height: 1.35;
  }

  .obs-mini {
    color: #64748b;
    font-size: 12px;
    font-weight: 750;
  }

  .obs-actions {
    display: grid;
    grid-template-columns: 150px 150px 1fr auto auto;
    gap: 8px;
    align-items: end;
    margin-top: 10px;
  }

  .obs-actions label {
    margin: 0;
  }

  .obs-actions span {
    display: block;
    margin-bottom: 5px;
    color: #1d3558;
    font-size: 11px;
    font-weight: 900;
  }

  .obs-actions select,
  .obs-actions textarea {
    width: 100%;
    min-height: 36px;
    padding: 7px 9px;
    border: 1px solid #d5e1ef;
    border-radius: 10px;
    background: #fff;
    color: #16304d;
    font-size: 12px;
    box-sizing: border-box;
  }

  .obs-actions textarea {
    min-height: 36px;
    resize: vertical;
  }

  .obs-suggest {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    margin-top: 8px;
  }

  .obs-tag {
    display: inline-flex;
    padding: 6px 9px;
    border-radius: 999px;
    border: 1px solid #f9d36a;
    background: #fff4d6;
    color: #8a4b00;
    font-size: 11px;
    font-weight: 900;
  }

  .obs-tag.danger {
    border-color: #fecaca;
    background: #fff0ee;
    color: #b42318;
  }

  .obs-tag.ok {
    border-color: #b9e5bf;
    background: #e8f7ea;
    color: #1f6b2a;
  }

  @media (max-width: 1100px) {
    .obs-grid,
    .obs-actions {
      grid-template-columns: 1fr;
    }
  }
</style>
@endsection

@section('content')

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

@php
  $swafiRoles = session('swafi_roles', []);
  $swafiPermissions = session('swafi_permissions', []);

  $isAdminSwafi = in_array('Administrador SWAFI', $swafiRoles, true);

  $canCreateExpedientes = $isAdminSwafi
      || in_array('expedientes.crear', $swafiPermissions, true);

  $canEditExpedientes = $isAdminSwafi
      || in_array('expedientes.editar', $swafiPermissions, true);

  $canManageDocuments = $isAdminSwafi
      || in_array('documentos.cargar', $swafiPermissions, true)
      || in_array('expedientes.editar', $swafiPermissions, true);

  $documentTypes = collect($documentos ?? [])
      ->pluck('tipo_documento')
      ->map(fn ($tipo) => strtoupper((string) $tipo))
      ->unique()
      ->values()
      ->all();

  $observaciones = $observaciones ?? collect();

  $observacionesAbiertas = collect($observaciones)
      ->filter(fn ($obs) => in_array($obs->estatus, ['abierta', 'en_proceso'], true));

  $correccionesDetectadas = [];

  if (!in_array('PDF', $documentTypes, true)) {
      $correccionesDetectadas[] = ['texto' => 'Falta PDF', 'clase' => 'danger'];
  }

  if (!in_array('XML', $documentTypes, true)) {
      $correccionesDetectadas[] = ['texto' => 'Falta XML', 'clase' => 'danger'];
  }

  if (!$valor) {
      $correccionesDetectadas[] = ['texto' => 'Falta valores fiscales/financieros', 'clase' => ''];
  }

  if ($expediente && empty($expediente->ubicacion_codigo)) {
      $correccionesDetectadas[] = ['texto' => 'Falta ubicación física', 'clase' => ''];
  }

  if ($expediente && $expediente->expediente_estatus === 'observado' && $observacionesAbiertas->isEmpty()) {
      $correccionesDetectadas[] = ['texto' => 'Estatus observado sin observación abierta', 'clase' => 'danger'];
  }

  if (empty($correccionesDetectadas)) {
      $correccionesDetectadas[] = ['texto' => 'Sin corrección pendiente detectada', 'clase' => 'ok'];
  }

  $tipoObservacionTexto = function (?string $tipo): string {
      return match ((string) $tipo) {
          'falta_pdf' => 'Falta PDF',
          'falta_xml' => 'Falta XML',
          'falta_valores' => 'Falta valores fiscales/financieros',
          'falta_ubicacion' => 'Falta ubicación física',
          'datos_inconsistentes' => 'Datos inconsistentes',
          'documento_incorrecto' => 'Documento incorrecto',
          default => 'Otro seguimiento',
      };
  };

  $estatusObservacionClass = function (?string $estatus): string {
      return match ((string) $estatus) {
          'cerrada' => 'ok',
          'cancelada' => 'warn',
          'en_proceso' => 'warn',
          default => 'danger',
      };
  };

  $prioridadClass = function (?string $prioridad): string {
      return in_array($prioridad, ['alta', 'critica'], true) ? 'danger' : 'warn';
  };
@endphp

@if(!$expediente)
  <section class="card">
    <div class="section-title">
      <h2>Detalle de expediente</h2>
      <span class="pill warn">Sin registros</span>
    </div>

    <p>No existen expedientes registrados todavía.</p>

    <div class="action-group">
      @if($canCreateExpedientes)
        <a class="tab" href="{{ route('registro-individual') }}">Ir a registro individual</a>
      @endif

      <a class="tab" href="{{ route('busqueda') }}">Ir a búsqueda avanzada</a>
    </div>
  </section>
@else

<section class="card">
  <div class="section-title">
    <h2>Detalle de expediente</h2>
    <span class="pill ok">Ficha ejecutiva</span>
  </div>

  <div class="action-group action-group-spaced">
    @if($canCreateExpedientes)
      <a class="tab" href="{{ route('registro-individual') }}">Nuevo registro</a>
    @endif

    <a class="tab" href="{{ route('busqueda') }}">Regresar a búsqueda</a>

    @if($canEditExpedientes)
      <a class="tab" href="{{ route('expedientes.editar', $expediente->expediente_id) }}">Editar expediente</a>
    @endif

    @if($documentos->count() > 0)
      <a class="tab" href="{{ route('documentos.descargar-todos', $expediente->expediente_id) }}">
        Descargar documentos ZIP
      </a>
    @else
      <span class="tab">Sin documentos para descargar</span>
    @endif
  </div>

  <div class="tabs">
    <span class="tab">Datos generales</span>
    <span class="tab">Activo fijo</span>
    <span class="tab">Valores</span>
    <span class="tab">Ubicación</span>
    <span class="tab">Documentos</span>
    <span class="tab">Seguimiento</span>
    <span class="tab">Historial</span>
  </div>

  <div class="meta-grid">
    <div class="meta-box">
      <strong>Folio factura</strong>
      <div>{{ $expediente->folio_factura }}</div>
    </div>

    <div class="meta-box">
      <strong>UUID CFDI</strong>
      <div>{{ $expediente->uuid_cfdi ?: 'No capturado' }}</div>
    </div>

    <div class="meta-box">
      <strong>ID activo</strong>
      <div>{{ $expediente->numero_activo }}</div>
    </div>

    <div class="meta-box">
      <strong>Proveedor</strong>
      <div>{{ $expediente->proveedor_nombre ?? 'Sin proveedor' }}</div>
    </div>

    <div class="meta-box">
      <strong>RFC</strong>
      <div>{{ $expediente->proveedor_rfc ?? 'Sin RFC' }}</div>
    </div>

    <div class="meta-box">
      <strong>Estatus documental</strong>
      <div>
        @php
          $pillClass = 'danger';

          if ($expediente->expediente_estatus === 'completo') {
              $pillClass = 'ok';
          } elseif ($expediente->expediente_estatus === 'observado') {
              $pillClass = 'warn';
          }
        @endphp

        <span class="pill {{ $pillClass }}">{{ ucfirst($expediente->expediente_estatus) }}</span>
      </div>
    </div>

    <div class="meta-box">
      <strong>Fecha factura</strong>
      <div>{{ $expediente->fecha_factura }}</div>
    </div>

    <div class="meta-box">
      <strong>Monto factura</strong>
      <div>$ {{ number_format((float) $expediente->monto_factura, 2) }} {{ $expediente->moneda }}</div>
    </div>

    <div class="meta-box">
      <strong>Tipo de activo</strong>
      <div>{{ $expediente->tipo_activo ?? 'Sin tipo' }}</div>
    </div>

    <div class="meta-box">
      <strong>Descripción</strong>
      <div>{{ $expediente->activo_descripcion }}</div>
    </div>

    <div class="meta-box">
      <strong>Serie / Marca / Modelo</strong>
      <div>
        {{ $expediente->serie ?: 'S/S' }} /
        {{ $expediente->marca ?: 'S/M' }} /
        {{ $expediente->modelo ?: 'S/M' }}
      </div>
    </div>

    <div class="meta-box">
      <strong>Planta</strong>
      <div>{{ $expediente->planta_nombre ?? 'Sin planta' }}</div>
    </div>

    <div class="meta-box">
      <strong>Centro de costo</strong>
      <div>
        {{ $expediente->centro_costo_clave ?? 'Sin centro' }}
        {{ $expediente->centro_costo_descripcion ? '- ' . $expediente->centro_costo_descripcion : '' }}
      </div>
    </div>

    <div class="meta-box">
      <strong>Ubicación física</strong>
      <div>
        {{ $expediente->ubicacion_descripcion ?? 'Sin ubicación' }}

        @if($expediente->ubicacion_codigo ?? false)
          <br><small>{{ $expediente->ubicacion_codigo }}</small>
        @endif

        @if($expediente->area_nombre ?? false)
          <br><small>Área: {{ $expediente->area_nombre }}</small>
        @endif
      </div>
    </div>

    <div class="meta-box">
      <strong>Responsable</strong>
      <div>
        {{ $expediente->responsable_nombre ?? 'Sin responsable' }}

        @if($expediente->responsable_correo ?? false)
          <br><small>{{ $expediente->responsable_correo }}</small>
        @endif
      </div>
    </div>
  </div>
</section>

<section class="card" style="margin-top:20px">
  <div class="section-title">
    <h2>Valores fiscales y financieros</h2>
    <span class="pill ok">M02 Control activo</span>
  </div>

  <div class="meta-grid">
    <div class="meta-box">
      <strong>Valor fiscal</strong>
      <div>{{ $valor ? '$ ' . number_format((float) $valor->valor_fiscal, 2) : 'Pendiente' }}</div>
    </div>

    <div class="meta-box">
      <strong>Valor financiero</strong>
      <div>{{ $valor ? '$ ' . number_format((float) $valor->valor_financiero, 2) : 'Pendiente' }}</div>
    </div>

    <div class="meta-box">
      <strong>Depreciación acumulada</strong>
      <div>{{ $valor ? '$ ' . number_format((float) $valor->depreciacion_acumulada, 2) : 'Pendiente' }}</div>
    </div>

    <div class="meta-box">
      <strong>Valor en libros</strong>
      <div>{{ $valor ? '$ ' . number_format((float) $valor->valor_en_libros, 2) : 'Pendiente' }}</div>
    </div>
  </div>
</section>

<section class="card" style="margin-top:20px">
  <div class="section-title">
    <h2>Seguimiento de observaciones</h2>
    <span class="pill {{ $observacionesAbiertas->count() > 0 ? 'warn' : 'ok' }}">
      {{ $observacionesAbiertas->count() }} abierta(s)
    </span>
  </div>

  <div class="obs-suggest">
    @foreach($correccionesDetectadas as $correccion)
      <span class="obs-tag {{ $correccion['clase'] }}">{{ $correccion['texto'] }}</span>
    @endforeach
  </div>

  <div class="obs-grid" style="margin-top:14px">
    @if($canEditExpedientes)
      <form method="POST" action="{{ route('observaciones.store', $expediente->expediente_id) }}" class="obs-form">
        @csrf

        <label>
          <span>Tipo de observación</span>
          <select name="tipo_observacion" required>
            <option value="">Selecciona una opción</option>
            <option value="falta_pdf">Falta PDF</option>
            <option value="falta_xml">Falta XML</option>
            <option value="falta_valores">Falta valores fiscales/financieros</option>
            <option value="falta_ubicacion">Falta ubicación física</option>
            <option value="datos_inconsistentes">Datos inconsistentes</option>
            <option value="documento_incorrecto">Documento incorrecto</option>
            <option value="otro">Otro seguimiento</option>
          </select>
        </label>

        <label>
          <span>Prioridad</span>
          <select name="prioridad" required>
            <option value="media">Media</option>
            <option value="alta">Alta</option>
            <option value="critica">Crítica</option>
            <option value="baja">Baja</option>
          </select>
        </label>

        <label>
          <span>Descripción de la observación</span>
          <textarea name="descripcion" required placeholder="Ej. Falta capturar valor financiero o adjuntar XML correcto.">{{ old('descripcion') }}</textarea>
        </label>

        <button class="tab" type="submit">Registrar observación</button>
      </form>
    @else
      <div class="obs-form">
        <strong>Seguimiento de solo lectura</strong>
        <p class="footer-note">Tu perfil puede consultar observaciones, pero no modificarlas.</p>
      </div>
    @endif

    <div class="obs-list">
      @forelse($observaciones as $observacion)
        <div class="obs-card">
          <div class="obs-card-head">
            <div>
              <strong>{{ $tipoObservacionTexto($observacion->tipo_observacion) }}</strong>
              <span class="obs-mini">
                Creada: {{ $observacion->created_at }}
                @if($observacion->creado_por_nombre)
                  · {{ $observacion->creado_por_nombre }}
                @endif
              </span>
            </div>

            <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end">
              <span class="pill {{ $estatusObservacionClass($observacion->estatus) }}">{{ str_replace('_', ' ', ucfirst($observacion->estatus)) }}</span>
              <span class="pill {{ $prioridadClass($observacion->prioridad) }}">Prioridad {{ ucfirst($observacion->prioridad) }}</span>
            </div>
          </div>

          <p>{{ $observacion->descripcion }}</p>

          @if($observacion->respuesta)
            <p><strong>Respuesta:</strong> {{ $observacion->respuesta }}</p>
          @endif

          @if($observacion->fecha_cierre)
            <span class="obs-mini">
              Cierre: {{ $observacion->fecha_cierre }}
              @if($observacion->cerrado_por_nombre)
                · {{ $observacion->cerrado_por_nombre }}
              @endif
            </span>
          @endif

          @if($canEditExpedientes && in_array($observacion->estatus, ['abierta', 'en_proceso'], true))
            <form method="POST" action="{{ route('observaciones.actualizar', $observacion->id) }}" class="obs-actions">
              @csrf
              @method('PATCH')

              <label>
                <span>Estatus</span>
                <select name="estatus" required>
                  <option value="abierta" {{ $observacion->estatus === 'abierta' ? 'selected' : '' }}>Abierta</option>
                  <option value="en_proceso" {{ $observacion->estatus === 'en_proceso' ? 'selected' : '' }}>En proceso</option>
                  <option value="cerrada">Cerrada</option>
                  <option value="cancelada">Cancelada</option>
                </select>
              </label>

              <label>
                <span>Prioridad</span>
                <select name="prioridad" required>
                  <option value="baja" {{ $observacion->prioridad === 'baja' ? 'selected' : '' }}>Baja</option>
                  <option value="media" {{ $observacion->prioridad === 'media' ? 'selected' : '' }}>Media</option>
                  <option value="alta" {{ $observacion->prioridad === 'alta' ? 'selected' : '' }}>Alta</option>
                  <option value="critica" {{ $observacion->prioridad === 'critica' ? 'selected' : '' }}>Crítica</option>
                </select>
              </label>

              <label>
                <span>Respuesta / cierre</span>
                <textarea name="respuesta" placeholder="Describe la corrección realizada.">{{ $observacion->respuesta }}</textarea>
              </label>

              <button class="tab" type="submit">Actualizar</button>
            </form>

            <form method="POST" action="{{ route('observaciones.cancelar', $observacion->id) }}" style="margin-top:8px" onsubmit="return confirm('¿Deseas cancelar esta observación? Se conservará la trazabilidad.');">
              @csrf
              @method('DELETE')
              <button type="submit" style="border:0;background:none;color:#b42318;font-weight:900;cursor:pointer;padding:0;">
                Cancelar observación
              </button>
            </form>
          @endif
        </div>
      @empty
        <div class="obs-card">
          <strong>Sin observaciones registradas</strong>
          <p class="footer-note">Cuando exista una corrección pendiente, se podrá registrar aquí para dar seguimiento hasta su cierre.</p>
        </div>
      @endforelse
    </div>
  </div>
</section>

<section class="card" style="margin-top:20px">
  <div class="section-title">
    <h2>Documentos asociados</h2>
    <span class="pill {{ $documentos->count() > 0 ? 'ok' : 'warn' }}">
      {{ $documentos->count() }} documento(s) vigente(s)
    </span>
  </div>

  @if($canManageDocuments)
    <form
      method="POST"
      action="{{ route('documentos.store', $expediente->expediente_id) }}"
      enctype="multipart/form-data"
      style="margin:14px 0 18px;padding:14px;border:1px dashed #b8cbe4;border-radius:16px;background:#f8fbff;"
    >
      @csrf

      <label style="display:block;margin-bottom:10px;">
        <strong style="display:block;margin-bottom:6px;color:#1d3558;font-size:13px;">
          Agregar o reemplazar documentos PDF/XML
        </strong>

        <input
          type="file"
          name="documentos[]"
          accept=".pdf,.xml"
          multiple
          required
          style="width:100%;min-height:40px;padding:8px;border:1px solid #d5e1ef;border-radius:11px;background:#fff;"
        >
      </label>

      <div class="footer-note" style="margin-bottom:10px;">
        Puedes seleccionar uno o varios documentos. Si cargas un archivo con el mismo nombre o mismo contenido,
        SWAFI lo sustituirá como una nueva versión. Si el documento es diferente, se sumará al expediente.
        El número de activo <strong>{{ $expediente->numero_activo }}</strong> no se duplica.
      </div>

      <button class="tab" type="submit">
        Ligar documentos al expediente
      </button>
    </form>
  @endif

  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>Documento</th>
          <th>Tipo</th>
          <th>Versión</th>
          <th>Estatus</th>
          <th>Tamaño</th>
          <th>Integridad</th>
          <th>Acciones</th>
        </tr>
      </thead>

      <tbody>
        @forelse($documentos as $documento)
          <tr>
            <td>
              <strong>{{ $documento->nombre_archivo }}</strong><br>
              <small>{{ $documento->mime_type ?: 'MIME no registrado' }}</small>
            </td>

            <td>{{ $documento->tipo_documento }}</td>

            <td>v{{ $documento->version }}</td>

            <td>
              <span class="pill {{ $documento->vigente ? 'ok' : 'warn' }}">
                {{ $documento->vigente ? 'Vigente' : 'No vigente' }}
              </span>
            </td>

            <td>
              @if($documento->tamano_bytes)
                {{ number_format(((float) $documento->tamano_bytes) / 1024, 2) }} KB
              @else
                No registrado
              @endif
            </td>

            <td>
              @if($documento->hash_sha256)
                <span class="pill ok">Hash SHA-256</span><br>
                <small>{{ substr($documento->hash_sha256, 0, 16) }}...</small>
              @else
                <span class="pill warn">Hash pendiente</span>
              @endif
            </td>

            <td>
              <div class="table-actions">
                <a href="{{ route('documentos.ver', $documento->id) }}" target="_blank" rel="noopener">
                  Ver
                </a>

                <a href="{{ route('documentos.descargar', $documento->id) }}">
                  Descargar
                </a>

                @if($canManageDocuments)
                  <form
                    method="POST"
                    action="{{ route('documentos.eliminar', $documento->id) }}"
                    style="display:inline"
                    onsubmit="return confirm('¿Deseas eliminar este documento del expediente? Se conservará la trazabilidad en bitácora.');"
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
            <td colspan="7">
              El expediente aún no cuenta con documentos PDF/XML vigentes asociados.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="footer-note" style="margin-top:12px">
    Los documentos se entregan desde almacenamiento privado. Antes de abrir o descargar, SWAFI valida existencia del archivo y coincidencia del hash SHA-256 registrado.
    La eliminación es lógica: el documento deja de aparecer como vigente, pero se conserva la trazabilidad en bitácora.
  </div>
</section>

<section class="card" style="margin-top:20px">
  <div class="section-title">
    <h2>Bitácora de auditoría</h2>
    <span class="pill ok">Trazabilidad</span>
  </div>

  <div class="list">
    @forelse($bitacora as $evento)
      <div class="list-item">
        <strong>{{ $evento->accion }} · {{ $evento->modulo }}</strong>
        <span>{{ $evento->fecha_evento }} · IP: {{ $evento->ip ?? 'No registrada' }}</span>
      </div>
    @empty
      <div class="list-item">
        <strong>Primer acceso registrado</strong>
        <span>La consulta actual comenzará a alimentar la bitácora.</span>
      </div>
    @endforelse
  </div>
</section>

@endif

@endsection
