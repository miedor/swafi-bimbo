@extends('layouts.app')

@section('title', 'Detalle de expediente | SWAFI')
@section('page_title', 'Detalle de expediente')
@section('page_subtitle', 'Vista integral del expediente documental y patrimonial')
@section('breadcrumb', 'Detalle de expediente')

@section('page_styles')
<style>
  .obs-workflow {
    display: grid;
    gap: 14px;
  }

  .obs-role-note {
    padding: 12px 14px;
    border: 1px solid #dbe7f6;
    border-radius: 16px;
    background: #f8fbff;
    color: #324b6d;
    font-size: 13px;
    font-weight: 750;
    line-height: 1.45;
  }

  .obs-form-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(180px, 1fr));
    gap: 12px;
  }

  .obs-form-grid .full {
    grid-column: 1 / -1;
  }

  .obs-field span {
    display: block;
    margin-bottom: 6px;
    color: #1d3558;
    font-size: 12px;
    font-weight: 900;
  }

  .obs-field input,
  .obs-field select,
  .obs-field textarea {
    width: 100%;
    min-height: 40px;
    padding: 9px 11px;
    border: 1px solid #d5e1ef;
    border-radius: 12px;
    background: #ffffff;
    color: #16304d;
    font-size: 13px;
    font-weight: 750;
  }

  .obs-field textarea {
    min-height: 86px;
    resize: vertical;
  }

  .obs-card {
    padding: 14px;
    border: 1px solid #dbe7f6;
    border-radius: 18px;
    background: #ffffff;
  }

  .obs-card + .obs-card {
    margin-top: 12px;
  }

  .obs-card-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
    margin-bottom: 10px;
  }

  .obs-card-head h3 {
    margin: 0;
    color: #152f52;
    font-size: 16px;
    font-weight: 950;
  }

  .obs-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  .obs-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 9px;
    border-radius: 999px;
    border: 1px solid #dbe7f6;
    background: #f8fbff;
    color: #174f9a;
    font-size: 11px;
    font-weight: 900;
    white-space: nowrap;
  }

  .obs-badge.warn {
    border-color: #f9d36a;
    background: #fff7db;
    color: #8a4b00;
  }

  .obs-badge.danger {
    border-color: #fecaca;
    background: #fff0ee;
    color: #b42318;
  }

  .obs-badge.ok {
    border-color: #b9e5bf;
    background: #e8f7ea;
    color: #1f6b2a;
  }

  .obs-body {
    display: grid;
    gap: 8px;
    color: #324b6d;
    font-size: 13px;
    line-height: 1.42;
  }

  .obs-body strong {
    color: #152f52;
  }

  .obs-actions {
    display: grid;
    gap: 10px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e5edf8;
  }

  .obs-action-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 10px;
    align-items: end;
  }

  .obs-action-row.validate {
    grid-template-columns: minmax(150px, .35fr) 1fr auto;
  }

  .obs-muted {
    color: #64748b;
    font-size: 12px;
    font-weight: 750;
  }

  @media (max-width: 980px) {
    .obs-form-grid,
    .obs-action-row,
    .obs-action-row.validate {
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
  $currentUserId = (int) (session('swafi_user_id') ?: auth()->id());

  $isAdminSwafi = in_array('Administrador SWAFI', $swafiRoles, true) || in_array('Administrador', $swafiRoles, true);
  $isCaptura = in_array('Usuario Captura', $swafiRoles, true) || in_array('Capturista', $swafiRoles, true);
  $isConsultaAuditoria = in_array('Usuario Consulta / Auditoría', $swafiRoles, true)
      || in_array('Usuario Consulta / Auditoria', $swafiRoles, true)
      || in_array('Consultor', $swafiRoles, true)
      || in_array('Auditor', $swafiRoles, true);
  $isPlantaInventarios = in_array('Usuario Planta / Inventarios', $swafiRoles, true);

  $canCreateExpedientes = $isAdminSwafi || in_array('expedientes.crear', $swafiPermissions, true);
  $canManageDocuments = $isAdminSwafi
      || in_array('documentos.cargar', $swafiPermissions, true)
      || in_array('expedientes.editar', $swafiPermissions, true);

  $canCreateObservation = $isAdminSwafi || $isConsultaAuditoria || in_array('observaciones.crear', $swafiPermissions, true);
  $canAttendObservation = $isAdminSwafi || in_array('observaciones.atender', $swafiPermissions, true);
  $canValidateObservation = $isAdminSwafi || $isConsultaAuditoria || in_array('observaciones.validar', $swafiPermissions, true);
  $usuariosAsignablesObservacion = $usuariosAsignablesObservacion ?? collect();

  $tipoObservacionLabels = [
      'falta_pdf' => 'Falta PDF',
      'falta_xml' => 'Falta XML',
      'falta_valores' => 'Falta valores fiscales/financieros',
      'falta_ubicacion' => 'Falta ubicación física',
      'ubicacion_incorrecta' => 'Ubicación incorrecta',
      'datos_inconsistentes' => 'Datos inconsistentes',
      'documento_incorrecto' => 'Documento incorrecto',
      'otro' => 'Otro seguimiento',
  ];

  $tipoObservacionOptions = $tipoObservacionLabels;

  $prioridadLabels = [
      'baja' => 'Baja',
      'media' => 'Media',
      'alta' => 'Alta',
      'critica' => 'Crítica',
  ];

  $estatusObservacionLabels = [
      'abierta' => 'Abierta',
      'en_atencion' => 'En atención',
      'atendida' => 'Atendida, pendiente de validar',
      'cerrada' => 'Cerrada',
      'rechazada' => 'Rechazada',
      'cancelada' => 'Cancelada',
  ];

  $obsBadgeClass = function (?string $estatus): string {
      return match ((string) $estatus) {
          'cerrada' => 'ok',
          'atendida', 'en_atencion' => 'warn',
          'rechazada', 'abierta' => 'danger',
          'cancelada' => '',
          default => '',
      };
  };

  $priorityBadgeClass = function (?string $prioridad): string {
      return match ((string) $prioridad) {
          'critica', 'alta' => 'danger',
          'media' => 'warn',
          'baja' => 'ok',
          default => '',
      };
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
    <span class="tab">Observaciones</span>
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
                <a href="{{ route('documentos.ver', $documento->id) }}" target="_blank" rel="noopener">Ver</a>
                <a href="{{ route('documentos.descargar', $documento->id) }}">Descargar</a>

                @if($canManageDocuments)
                  <form
                    method="POST"
                    action="{{ route('documentos.eliminar', $documento->id) }}"
                    style="display:inline"
                    onsubmit="return confirm('¿Deseas eliminar este documento del expediente? Se conservará la trazabilidad en bitácora.');"
                  >
                    @csrf
                    @method('DELETE')

                    <button type="submit" style="border:0;background:none;color:#b42318;font-weight:800;cursor:pointer;padding:0">
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
    <h2>Seguimiento y validación cruzada</h2>
    <span class="pill {{ ($observaciones ?? collect())->whereIn('estatus', ['abierta', 'en_atencion', 'atendida', 'rechazada'])->count() > 0 ? 'warn' : 'ok' }}">
      {{ ($observaciones ?? collect())->whereIn('estatus', ['abierta', 'en_atencion', 'atendida', 'rechazada'])->count() }} pendiente(s)
    </span>
  </div>

  <div class="obs-workflow">
    <div class="obs-role-note">
      <strong>Flujo de valor:</strong>
      Consulta/Auditoría o Planta registran observaciones; Captura o Planta atienden la corrección;
      Consulta/Auditoría valida y cierra o rechaza. El usuario que atiende no puede validar su propia corrección.
    </div>

    @if($canCreateObservation)
      <form method="POST" action="{{ route('observaciones.store', $expediente->expediente_id) }}" class="obs-card">
        @csrf

        <div class="obs-card-head">
          <h3>Registrar nueva observación</h3>
          <span class="obs-badge warn">Asigna responsable y envía correo</span>
        </div>

        <div class="obs-form-grid">
          <label class="obs-field">
            <span>Tipo de observación</span>
            <select name="tipo_observacion" id="obs_tipo_observacion" required>
              <option value="">Selecciona</option>
              @foreach($tipoObservacionOptions as $key => $label)
                <option value="{{ $key }}" {{ old('tipo_observacion') === $key ? 'selected' : '' }}>
                  {{ $label }}
                </option>
              @endforeach
            </select>
          </label>

          <label class="obs-field">
            <span>Prioridad</span>
            <select name="prioridad" required>
              @foreach($prioridadLabels as $key => $label)
                <option value="{{ $key }}" {{ old('prioridad', 'media') === $key ? 'selected' : '' }}>
                  {{ $label }}
                </option>
              @endforeach
            </select>
          </label>

          <label class="obs-field">
            <span>Rol responsable</span>
            <select name="rol_destino" id="obs_rol_destino" required>
              <option value="Usuario Captura" {{ old('rol_destino') === 'Usuario Captura' ? 'selected' : '' }}>
                Usuario Captura
              </option>
              <option value="Usuario Planta / Inventarios" {{ old('rol_destino') === 'Usuario Planta / Inventarios' ? 'selected' : '' }}>
                Usuario Planta / Inventarios
              </option>
            </select>
          </label>

          <label class="obs-field full">
            <span>Usuario que atenderá la observación</span>
            <select name="asignado_a" id="obs_asignado_a" required>
              <option value="">Selecciona usuario responsable</option>
              @foreach($usuariosAsignablesObservacion as $usuarioAsignable)
                <option
                  value="{{ $usuarioAsignable->id }}"
                  data-rol="{{ $usuarioAsignable->rol_nombre }}"
                  {{ (string) old('asignado_a') === (string) $usuarioAsignable->id ? 'selected' : '' }}
                >
                  {{ $usuarioAsignable->name }} · {{ $usuarioAsignable->rol_nombre }} · {{ $usuarioAsignable->email }}
                </option>
              @endforeach
            </select>
            <div class="obs-muted" style="margin-top:6px">
              Regla: observaciones de ubicación se asignan a Usuario Planta / Inventarios; observaciones documentales, valores o datos se asignan a Usuario Captura.
            </div>
          </label>

          <label class="obs-field full">
            <span>Descripción de la observación</span>
            <textarea name="descripcion" required placeholder="Describe claramente qué se detectó y qué debe corregirse">{{ old('descripcion') }}</textarea>
          </label>
        </div>

        <div style="margin-top:12px">
          <button type="submit" class="tab">Registrar y notificar observación</button>
        </div>
      </form>
    @endif

    <div>
      @forelse(($observaciones ?? collect()) as $observacion)
        @php
          $estatus = (string) $observacion->estatus;
          $tipo = $tipoObservacionLabels[$observacion->tipo_observacion] ?? $observacion->tipo_observacion;
          $prioridad = $prioridadLabels[$observacion->prioridad] ?? $observacion->prioridad;
          $assignedToCurrentUser = empty($observacion->asignado_a) || (int) $observacion->asignado_a === $currentUserId;
          $canTakeThis = $canAttendObservation
              && in_array($estatus, ['abierta', 'rechazada'], true)
              && ($isAdminSwafi || $assignedToCurrentUser)
              && ($isAdminSwafi || (int) $observacion->creado_por !== $currentUserId);
          $canAttendThis = $canAttendObservation
              && in_array($estatus, ['abierta', 'en_atencion', 'rechazada'], true)
              && ($isAdminSwafi || $assignedToCurrentUser)
              && ($isAdminSwafi || (int) $observacion->creado_por !== $currentUserId);
          $canValidateThis = $canValidateObservation
              && $estatus === 'atendida'
              && ($isAdminSwafi || (int) $observacion->atendido_por !== $currentUserId);
          $canCancelThis = $canValidateObservation && !in_array($estatus, ['cerrada', 'cancelada'], true);
        @endphp

        <article class="obs-card">
          <div class="obs-card-head">
            <h3>{{ $tipo }}</h3>
            <div class="obs-badges">
              <span class="obs-badge {{ $obsBadgeClass($estatus) }}">{{ $estatusObservacionLabels[$estatus] ?? ucfirst($estatus) }}</span>
              <span class="obs-badge {{ $priorityBadgeClass($observacion->prioridad) }}">Prioridad {{ $prioridad }}</span>
            </div>
          </div>

          <div class="obs-body">
            <div><strong>Observación:</strong> {{ $observacion->descripcion }}</div>
            <div class="obs-muted">
              Registró: {{ $observacion->creado_por_nombre ?: ($observacion->creado_por_email ?: 'Usuario no identificado') }} · {{ $observacion->created_at }}
            </div>
            <div class="obs-muted">
              Asignado a: {{ $observacion->asignado_a_nombre ?: ($observacion->asignado_a_email ?: 'Usuario pendiente de asignar') }}
              @if($observacion->rol_destino)
                · Rol: {{ $observacion->rol_destino }}
              @endif
              @if($observacion->fecha_notificacion)
                · Correo enviado: {{ $observacion->fecha_notificacion }}
              @elseif($observacion->notificacion_error)
                · <span style="color:#b42318;font-weight:900">Correo no enviado</span>
              @endif
            </div>

            @if($observacion->respuesta_atencion)
              <div><strong>Respuesta de atención:</strong> {{ $observacion->respuesta_atencion }}</div>
              <div class="obs-muted">
                Atendió: {{ $observacion->atendido_por_nombre ?: ($observacion->atendido_por_email ?: 'Usuario no identificado') }} · {{ $observacion->fecha_atencion ?: $observacion->updated_at }}
              </div>
            @endif

            @if($observacion->comentario_validacion)
              <div><strong>Validación:</strong> {{ $observacion->comentario_validacion }}</div>
              <div class="obs-muted">
                Validó/Canceló:
                {{ $observacion->validado_por_nombre ?: ($observacion->validado_por_email ?: ($observacion->cancelado_por_nombre ?: ($observacion->cancelado_por_email ?: 'Usuario no identificado'))) }}
                · {{ $observacion->fecha_validacion ?: ($observacion->fecha_cancelacion ?: $observacion->updated_at) }}
              </div>
            @endif
          </div>

          @if($canTakeThis || $canAttendThis || $canValidateThis || $canCancelThis)
            <div class="obs-actions">
              @if($canTakeThis)
                <form method="POST" action="{{ route('observaciones.tomar', $observacion->id) }}">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="tab">Tomar en atención</button>
                </form>
              @endif

              @if($canAttendThis)
                <form method="POST" action="{{ route('observaciones.atender', $observacion->id) }}" class="obs-action-row">
                  @csrf
                  @method('PATCH')
                  <label class="obs-field">
                    <span>Respuesta de atención</span>
                    <textarea name="respuesta_atencion" required placeholder="Describe la corrección realizada, documento cargado, dato corregido o acción aplicada">{{ old('respuesta_atencion') }}</textarea>
                  </label>
                  <button type="submit" class="tab">Marcar como atendida</button>
                </form>
              @endif

              @if($canValidateThis)
                <form method="POST" action="{{ route('observaciones.validar', $observacion->id) }}" class="obs-action-row validate">
                  @csrf
                  @method('PATCH')
                  <label class="obs-field">
                    <span>Decisión</span>
                    <select name="decision" required>
                      <option value="cerrada">Cerrar corrección</option>
                      <option value="rechazada">Rechazar corrección</option>
                    </select>
                  </label>
                  <label class="obs-field">
                    <span>Comentario de validación</span>
                    <textarea name="comentario_validacion" required placeholder="Indica si la evidencia quedó correcta o por qué se rechaza">{{ old('comentario_validacion') }}</textarea>
                  </label>
                  <button type="submit" class="tab">Validar</button>
                </form>
              @endif

              @if($canCancelThis)
                <form
                  method="POST"
                  action="{{ route('observaciones.cancelar', $observacion->id) }}"
                  onsubmit="return confirm('¿Deseas cancelar esta observación? Se conservará trazabilidad en bitácora.');"
                >
                  @csrf
                  @method('DELETE')
                  <input type="hidden" name="comentario_validacion" value="Observación cancelada por Consulta/Auditoría.">
                  <button type="submit" style="border:0;background:none;color:#b42318;font-weight:900;cursor:pointer;padding:0">
                    Cancelar observación
                  </button>
                </form>
              @endif
            </div>
          @endif
        </article>
      @empty
        <div class="obs-card">
          <div class="obs-body">
            <strong>Sin observaciones de seguimiento.</strong>
            <span>El expediente no tiene observaciones abiertas, atendidas, rechazadas, cerradas o canceladas.</span>
          </div>
        </div>
      @endforelse
    </div>
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


@section('page_scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const tipo = document.getElementById('obs_tipo_observacion');
    const rol = document.getElementById('obs_rol_destino');
    const usuario = document.getElementById('obs_asignado_a');

    if (!tipo || !rol || !usuario) {
      return;
    }

    const tiposPlanta = ['falta_ubicacion', 'ubicacion_incorrecta'];

    function syncRoleByType() {
      if (tiposPlanta.includes(tipo.value)) {
        rol.value = 'Usuario Planta / Inventarios';
      } else if (tipo.value !== '') {
        rol.value = 'Usuario Captura';
      }

      filterUsersByRole();
    }

    function filterUsersByRole() {
      const selectedRole = rol.value;
      let visibleSelected = false;

      Array.from(usuario.options).forEach(function (option) {
        if (option.value === '') {
          option.hidden = false;
          return;
        }

        const optionRole = option.getAttribute('data-rol');
        const visible = optionRole === selectedRole;
        option.hidden = !visible;

        if (visible && option.selected) {
          visibleSelected = true;
        }
      });

      if (!visibleSelected) {
        usuario.value = '';
      }
    }

    tipo.addEventListener('change', syncRoleByType);
    rol.addEventListener('change', filterUsersByRole);

    syncRoleByType();
  });
</script>
@endsection
