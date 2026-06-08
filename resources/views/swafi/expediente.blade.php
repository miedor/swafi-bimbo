@extends('layouts.app')

@section('title', 'Detalle de expediente | SWAFI')
@section('page_title', 'Detalle de expediente')
@section('page_subtitle', 'Vista integral del expediente documental y patrimonial')
@section('breadcrumb', 'Detalle de expediente')

@section('content')

@if(!$expediente)
  <section class="card">
    <div class="section-title">
      <h2>Detalle de expediente</h2>
      <span class="pill warn">Sin registros</span>
    </div>
    <p>No existen expedientes registrados todavía. Primero registra un expediente individual.</p>
    <div class="action-group">
      <a class="tab" href="{{ route('registro-individual') }}">Ir a registro individual</a>
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
    <a class="tab" href="{{ route('registro-individual') }}">Editar</a>
    <a class="tab" href="{{ route('busqueda') }}">Regresar a búsqueda</a>
    <span class="tab">Descargar</span>
  </div>

  <div class="tabs">
    <span class="tab">Datos generales</span>
    <span class="tab">Activo fijo</span>
    <span class="tab">Valores</span>
    <span class="tab">Ubicación</span>
    <span class="tab">Documentos</span>
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
          $pillClass = match($expediente->expediente_estatus) {
            'completo' => 'ok',
            'observado' => 'warn',
            default => 'danger',
          };
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
        @if($expediente->codigo_interno ?? false)
          <br><small>{{ $expediente->codigo_interno }}</small>
        @endif
      </div>
    </div>

    <div class="meta-box">
      <strong>Responsable</strong>
      <div>{{ $expediente->responsable_nombre ?? 'Sin responsable' }}</div>
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
      {{ $documentos->count() }} documento(s)
    </span>
  </div>

  <div class="list">
    @forelse($documentos as $documento)
      <div class="list-item">
        <strong>{{ $documento->tipo_documento }} · {{ $documento->nombre_archivo }}</strong>
        <span>
          Versión {{ $documento->version }}
          · {{ $documento->vigente ? 'Vigente' : 'No vigente' }}
          · {{ $documento->hash_sha256 ? 'Hash registrado' : 'Hash pendiente' }}
        </span>
      </div>
    @empty
      <div class="list-item">
        <strong>Sin documentos registrados</strong>
        <span>El expediente aún no cuenta con referencias PDF/XML.</span>
      </div>
    @endforelse
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
