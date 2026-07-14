<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Etiqueta QR {{ $activo->numero_activo }} | SWAFI</title>

    @vite('resources/js/swafi-qr.js')

    <style>
        :root {
            font-family: Arial, Helvetica, sans-serif;
            color: #13243a;
            background: #eef3f8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            padding: 24px;
        }

        .toolbar {
            width: min(980px, 100%);
            margin: 0 auto 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
        }

        .toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .button {
            appearance: none;
            border: 1px solid #1c5d99;
            border-radius: 8px;
            background: #ffffff;
            color: #174f82;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 9px 16px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }

        .button.primary {
            background: #174f82;
            color: #ffffff;
        }

        .button:disabled {
            cursor: not-allowed;
            opacity: .55;
        }

        .label-sheet {
            width: min(980px, 100%);
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #cbd7e3;
            border-radius: 14px;
            box-shadow: 0 12px 35px rgba(20, 48, 80, .12);
            padding: 24px;
        }

        .label-card {
            width: 100%;
            min-height: 380px;
            border: 3px solid #174f82;
            border-radius: 14px;
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(280px, .8fr);
            overflow: hidden;
            background: #ffffff;
        }

        .label-info {
            padding: 24px 28px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .brand-row {
            display: flex;
            align-items: center;
            gap: 16px;
            border-bottom: 2px solid #d8e4ef;
            padding-bottom: 14px;
        }

        .brand-row img {
            width: 122px;
            height: auto;
            display: block;
        }

        .brand-copy strong {
            display: block;
            font-size: 24px;
            letter-spacing: .04em;
        }

        .brand-copy span {
            display: block;
            margin-top: 4px;
            color: #52677d;
            font-size: 13px;
        }

        .asset-number {
            margin: 0;
            color: #174f82;
            font-size: clamp(36px, 6vw, 62px);
            line-height: 1;
            letter-spacing: .03em;
            overflow-wrap: anywhere;
        }

        .asset-description {
            margin: -8px 0 0;
            font-size: 20px;
            line-height: 1.35;
            font-weight: 700;
        }

        .data-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 20px;
        }

        .data-item {
            border-left: 4px solid #d8e4ef;
            padding-left: 10px;
            min-width: 0;
        }

        .data-item span {
            display: block;
            color: #60758a;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .data-item strong {
            display: block;
            margin-top: 4px;
            color: #13243a;
            font-size: 15px;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }

        .status-row {
            margin-top: auto;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: #e8f1f9;
            color: #174f82;
            padding: 7px 11px;
            font-size: 12px;
            font-weight: 700;
        }

        .label-qr {
            border-left: 3px solid #174f82;
            padding: 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: #f8fbfe;
        }

        .label-qr canvas {
            width: min(300px, 100%);
            height: auto;
            border: 10px solid #ffffff;
            box-shadow: 0 0 0 1px #cbd7e3;
        }

        .qr-title {
            margin: 16px 0 4px;
            font-size: 18px;
            font-weight: 800;
        }

        .qr-help {
            margin: 0;
            color: #52677d;
            font-size: 12px;
            line-height: 1.45;
        }

        .qr-status {
            margin: 10px 0 0;
            color: #23734b;
            font-size: 12px;
            font-weight: 700;
        }

        .qr-status.is-error {
            color: #9d2f2f;
        }

        .technical-note {
            width: min(980px, 100%);
            margin: 12px auto 0;
            color: #5a6d80;
            font-size: 12px;
            line-height: 1.5;
        }

        @media (max-width: 760px) {
            body {
                padding: 12px;
            }

            .label-sheet {
                padding: 12px;
            }

            .label-card {
                grid-template-columns: 1fr;
            }

            .label-qr {
                border-left: 0;
                border-top: 3px solid #174f82;
            }

            .data-grid {
                grid-template-columns: 1fr;
            }
        }

        @page {
            size: landscape;
            margin: 8mm;
        }

        @media print {
            :root,
            body {
                background: #ffffff;
            }

            body {
                min-height: auto;
                padding: 0;
            }

            .toolbar,
            .technical-note {
                display: none !important;
            }

            .label-sheet {
                width: 100%;
                margin: 0;
                padding: 0;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .label-card {
                min-height: 0;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    @php
        $ubicacion = array_values(array_filter([
            $activo->ubicacion_codigo,
            $activo->ubicacion_descripcion,
            $activo->edificio,
            $activo->piso,
            $activo->pasillo,
        ], fn ($value) => $value !== null && trim((string) $value) !== ''));

        $estatusOperativo = ucfirst(str_replace('_', ' ', (string) ($activo->estatus_operativo ?? 'sin estatus')));
        $estatusDocumental = ucfirst(str_replace('_', ' ', (string) ($activo->estatus_documental ?? 'sin estatus')));
        $estatusInventario = $activo->estatus_localizacion
            ? ucfirst(str_replace('_', ' ', (string) $activo->estatus_localizacion))
            : 'Sin inventario';
    @endphp

    <div class="toolbar">
        <div>
            <strong>Etiqueta de identificación física SWAFI</strong><br>
            <small>Historia de usuario HU-049 · generación bajo demanda</small>
        </div>

        <div class="toolbar-actions">
            <a class="button" href="{{ route('ubicacion', ['numero_activo' => $activo->numero_activo]) }}">Regresar</a>
            <button class="button" type="button" data-download-qr disabled>Descargar QR PNG</button>
            <button class="button" type="button" data-download-pdf disabled>Descargar etiqueta PDF</button>
            <button class="button primary" type="button" data-print-label disabled>Imprimir etiqueta</button>
        </div>
    </div>

    <main class="label-sheet">
        <section class="label-card" aria-label="Etiqueta del activo {{ $activo->numero_activo }}">
            <div class="label-info">
                <div class="brand-row">
                    <img src="{{ asset('assets/swafi/img/logo-bimbo.jpg') }}" alt="Bimbo" data-label-logo>
                    <div class="brand-copy">
                        <strong>SWAFI</strong>
                        <span>Sistema Web de Gestión de Facturas de Activo Fijo</span>
                    </div>
                </div>

                <h1 class="asset-number">{{ $activo->numero_activo }}</h1>
                <p class="asset-description">{{ $activo->descripcion }}</p>

                <div class="data-grid">
                    <div class="data-item">
                        <span>Tipo de activo</span>
                        <strong>{{ $activo->tipo_activo ?? 'Sin clasificación' }}</strong>
                    </div>

                    <div class="data-item">
                        <span>Planta</span>
                        <strong>{{ $activo->planta_nombre ?? 'Sin planta' }}</strong>
                    </div>

                    <div class="data-item">
                        <span>Área / ubicación</span>
                        <strong>{{ $ubicacion ? implode(' / ', $ubicacion) : 'Sin ubicación registrada' }}</strong>
                    </div>

                    <div class="data-item">
                        <span>Responsable</span>
                        <strong>{{ $activo->responsable_nombre ?? 'Sin responsable asignado' }}</strong>
                    </div>

                    <div class="data-item">
                        <span>Marca / modelo</span>
                        <strong>{{ trim(implode(' / ', array_filter([$activo->marca, $activo->modelo]))) ?: 'Sin dato' }}</strong>
                    </div>

                    <div class="data-item">
                        <span>Serie</span>
                        <strong>{{ $activo->serie ?: 'Sin dato' }}</strong>
                    </div>
                </div>

                <div class="status-row">
                    <span class="pill">Operativo: {{ $estatusOperativo }}</span>
                    <span class="pill">Documental: {{ $estatusDocumental }}</span>
                    <span class="pill">Inventario: {{ $estatusInventario }}</span>
                </div>
            </div>

            <div class="label-qr">
                <canvas
                    width="300"
                    height="300"
                    data-swafi-qr="{{ $qrUrl }}"
                    data-audit-url="{{ route('activos.etiqueta.auditar', $activo->numero_activo) }}"
                    data-download-name="{{ $nombreArchivo }}"
                    data-pdf-name="{{ $nombreArchivoPdf }}"
                    data-asset-number="{{ $activo->numero_activo }}"
                    data-asset-description="{{ $activo->descripcion }}"
                    data-asset-type="{{ $activo->tipo_activo ?? 'Sin clasificación' }}"
                    data-plant="{{ $activo->planta_nombre ?? 'Sin planta' }}"
                    data-location="{{ $ubicacion ? implode(' / ', $ubicacion) : 'Sin ubicación registrada' }}"
                    data-responsible="{{ $activo->responsable_nombre ?? 'Sin responsable asignado' }}"
                    data-brand-model="{{ trim(implode(' / ', array_filter([$activo->marca, $activo->modelo]))) ?: 'Sin dato' }}"
                    data-serial="{{ $activo->serie ?: 'Sin dato' }}"
                    data-operational-status="{{ $estatusOperativo }}"
                    data-document-status="{{ $estatusDocumental }}"
                    data-inventory-status="{{ $estatusInventario }}"
                    data-generated-at="{{ now()->format('d/m/Y H:i') }}"
                    aria-label="Código QR del activo {{ $activo->numero_activo }}"
                ></canvas>

                <p class="qr-title">Escanear para consultar</p>
                <p class="qr-help">
                    El código dirige a la búsqueda autenticada del activo en SWAFI.<br>
                    Generado: {{ now()->format('d/m/Y H:i') }}
                </p>
                <p class="qr-status" data-qr-status>Generando código QR…</p>
            </div>
        </section>
    </main>

    <p class="technical-note">
        La etiqueta no sustituye el número físico del activo ni los controles corporativos de Bimbo SA de CV.
        La generación, descarga e impresión se registran en la bitácora de auditoría de SWAFI.
    </p>
</body>
</html>
