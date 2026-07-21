<?php

namespace App\Services;

use App\Models\CfdiValidacion;
use App\Models\DocumentoExpediente;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CfdiValidationService
{
    public function __construct(private readonly SwafiStorageService $storage)
    {
    }

    public function validateDocument(DocumentoExpediente $document, ?int $userId = null): CfdiValidacion
    {
        if (strtoupper((string) $document->tipo_documento) !== 'XML') {
            throw new RuntimeException('El documento seleccionado no es un XML CFDI.');
        }

        if (!Schema::hasTable('cfdi_validaciones')) {
            throw new RuntimeException('La tabla cfdi_validaciones aún no existe. Ejecuta las migraciones.');
        }

        $context = DB::table('documentos_expediente as d')
            ->join('expedientes as e', 'e.id', '=', 'd.expediente_id')
            ->where('d.id', $document->id)
            ->whereNull('e.deleted_at')
            ->select([
                'd.id as documento_id',
                'd.ruta_archivo',
                'd.storage_disk',
                'd.nombre_archivo',
                'd.hash_sha256',
                'd.vigente',
                'e.id as expediente_id',
                'e.numero_activo',
            ])
            ->first();

        if (!$context) {
            throw new RuntimeException('No fue posible recuperar el contexto del XML.');
        }

        $storageValidation = $this->storage->validate(
            $context->storage_disk ?? null,
            (string) $context->ruta_archivo,
            $context->hash_sha256 ?: null
        );

        if (!$storageValidation['ok']) {
            $validation = $this->persistInvalidValidation(
                context: $context,
                userId: $userId,
                errors: [$storageValidation['message'] ?: 'El archivo XML no fue localizado o no superó la validación de integridad.'],
                extracted: [
                    'nombre_archivo' => $context->nombre_archivo,
                    'storage_disk' => $context->storage_disk ?? 'local',
                    'hash_registrado' => $context->hash_sha256,
                    'hash_actual' => $storageValidation['hash_sha256'] ?? null,
                ]
            );

            $this->recalculateExpedienteStatus((int) $context->expediente_id, (string) $context->numero_activo, $userId);

            return $validation;
        }

        $xml = $this->storage->contents(
            $storageValidation['disk'],
            $storageValidation['path']
        );

        $extracted = $this->extractFromString($xml);
        $structuralErrors = $extracted['errors'];
        $errors = array_values(array_unique($structuralErrors));
        $warnings = array_values(array_unique($extracted['warnings']));
        $informational = [
            'Los datos extraídos del XML se conservan como referencia documental. SWAFI no los compara contra el activo, el proveedor, el folio, el UUID, la fecha, el monto, la moneda ni los valores fiscales o financieros registrados.',
        ];

        $status = 'valido';

        if (!$extracted['xml_bien_formado'] || !empty($structuralErrors)) {
            $status = 'invalido';
        } elseif (!empty($warnings)) {
            $status = 'observado';
        }

        $validation = CfdiValidacion::updateOrCreate(
            ['documento_id' => $context->documento_id],
            [
                'expediente_id' => $context->expediente_id,
                'numero_activo' => $context->numero_activo,
                'version_cfdi' => $extracted['version_cfdi'],
                'uuid_cfdi' => $extracted['uuid_cfdi'],
                'rfc_emisor' => $extracted['rfc_emisor'],
                'nombre_emisor' => $extracted['nombre_emisor'],
                'rfc_receptor' => $extracted['rfc_receptor'],
                'fecha_emision' => $extracted['fecha_emision'],
                'subtotal' => $extracted['subtotal'],
                'descuento' => $extracted['descuento'],
                'total' => $extracted['total'],
                'moneda' => $extracted['moneda'],
                'tipo_cambio' => $extracted['tipo_cambio'],
                'tipo_comprobante' => $extracted['tipo_comprobante'],
                'metodo_pago' => $extracted['metodo_pago'],
                'forma_pago' => $extracted['forma_pago'],
                'lugar_expedicion' => $extracted['lugar_expedicion'],
                'xml_bien_formado' => $extracted['xml_bien_formado'],
                'sello_presente' => $extracted['sello_presente'],
                'certificado_presente' => $extracted['certificado_presente'],
                'timbre_presente' => $extracted['timbre_presente'],
                'coincide_uuid' => null,
                'coincide_rfc' => null,
                'coincide_fecha' => null,
                'coincide_monto' => null,
                'coincide_moneda' => null,
                'diferencia_monto' => null,
                'estatus_validacion' => $status,
                'errores' => $errors ?: null,
                'advertencias' => $warnings ?: null,
                'datos_extraidos' => array_merge(
                    $extracted['raw'],
                    $informational ? ['informacion' => $informational] : []
                ),
                'validado_por' => $userId,
                'validado_at' => now(),
            ]
        );

        $this->reconcileStoredValue((string) $context->numero_activo, $validation, $userId);
        $this->recalculateExpedienteStatus((int) $context->expediente_id, (string) $context->numero_activo, $userId);
        $this->registerAudit(
            numeroActivo: (string) $context->numero_activo,
            userId: $userId,
            action: 'VALIDACION_CFDI',
            table: 'cfdi_validaciones',
            key: (string) $validation->id,
            after: [
                'documento_id' => $context->documento_id,
                'estatus_validacion' => $status,
                'errores' => $errors,
                'advertencias' => $warnings,
                'informacion' => $informational,
            ]
        );

        return $validation;
    }

    public function validateExpedienteXmls(int $expedienteId, ?int $userId = null): array
    {
        $documents = DocumentoExpediente::query()
            ->where('expediente_id', $expedienteId)
            ->whereRaw('UPPER(tipo_documento) = ?', ['XML'])
            ->where('vigente', true)
            ->orderBy('id')
            ->get();

        $summary = [
            'procesados' => 0,
            'validos' => 0,
            'observados' => 0,
            'invalidados' => 0,
        ];

        foreach ($documents as $document) {
            $validation = $this->validateDocument($document, $userId);
            $summary['procesados']++;

            if ($validation->estatus_validacion === 'valido') {
                $summary['validos']++;
            } elseif ($validation->estatus_validacion === 'observado') {
                $summary['observados']++;
            } else {
                $summary['invalidados']++;
            }
        }

        $expediente = DB::table('expedientes')->where('id', $expedienteId)->whereNull('deleted_at')->first();

        if ($expediente) {
            $this->recalculateExpedienteStatus($expedienteId, (string) $expediente->numero_activo, $userId);
        }

        return $summary;
    }

    /**
     * Extrae datos básicos de CFDI 3.3/4.0 sin resolver entidades externas.
     * Esta validación es técnica, de integridad y seguridad; no compara el XML contra los datos del activo ni sustituye la validación en línea ante SAT.
     */
    public function extractFromString(string $xml): array
    {
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            return $this->emptyExtraction([
                'El XML contiene una declaración DOCTYPE o ENTITY no permitida por seguridad.',
            ]);
        }

        if (!class_exists(DOMDocument::class) || !class_exists(DOMXPath::class)) {
            return $this->extractWithoutDom($xml);
        }

        $errors = [];
        $warnings = [];
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOCDATA);
        $libxmlErrors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || !$dom->documentElement instanceof DOMElement) {
            foreach ($libxmlErrors as $libxmlError) {
                $errors[] = trim((string) $libxmlError->message);
            }

            if (empty($errors)) {
                $errors[] = 'El archivo no contiene un XML bien formado.';
            }

            return $this->emptyExtraction($errors);
        }

        $root = $dom->documentElement;
        $xpath = new DOMXPath($dom);
        $emisor = $xpath->query('//*[local-name()="Emisor"]')->item(0);
        $receptor = $xpath->query('//*[local-name()="Receptor"]')->item(0);
        $timbre = $xpath->query('//*[local-name()="TimbreFiscalDigital"]')->item(0);

        $data = [
            'xml_bien_formado' => true,
            'version_cfdi' => $this->attribute($root, ['Version', 'version']),
            'uuid_cfdi' => $this->upper($this->attribute($timbre, ['UUID', 'Uuid', 'uuid'])),
            'rfc_emisor' => $this->upper($this->attribute($emisor, ['Rfc', 'rfc', 'RFC'])),
            'nombre_emisor' => $this->attribute($emisor, ['Nombre', 'nombre']),
            'rfc_receptor' => $this->upper($this->attribute($receptor, ['Rfc', 'rfc', 'RFC'])),
            'fecha_emision' => $this->parseDateTime($this->attribute($root, ['Fecha', 'fecha'])),
            'subtotal' => $this->decimal($this->attribute($root, ['SubTotal', 'subtotal', 'SubTotal'])),
            'descuento' => $this->decimal($this->attribute($root, ['Descuento', 'descuento'])),
            'total' => $this->decimal($this->attribute($root, ['Total', 'total'])),
            'moneda' => $this->upper($this->attribute($root, ['Moneda', 'moneda'])),
            'tipo_cambio' => $this->decimal($this->attribute($root, ['TipoCambio', 'tipoCambio', 'tipocambio']), 6),
            'tipo_comprobante' => $this->upper($this->attribute($root, ['TipoDeComprobante', 'tipoDeComprobante'])),
            'metodo_pago' => $this->upper($this->attribute($root, ['MetodoPago', 'metodoPago'])),
            'forma_pago' => $this->upper($this->attribute($root, ['FormaPago', 'formaPago'])),
            'lugar_expedicion' => $this->attribute($root, ['LugarExpedicion', 'lugarExpedicion']),
            'sello_presente' => $this->filledAttribute($root, ['Sello', 'sello']),
            'certificado_presente' => $this->filledAttribute($root, ['Certificado', 'certificado', 'NoCertificado', 'noCertificado']),
            'timbre_presente' => $timbre instanceof DOMElement,
            'errors' => [],
            'warnings' => [],
            'raw' => [],
        ];

        if (!in_array($data['version_cfdi'], ['3.3', '4.0'], true)) {
            $warnings[] = 'La versión CFDI no corresponde a 3.3 o 4.0; se extrajeron los datos disponibles.';
        }

        if (!$data['uuid_cfdi']) {
            $errors[] = 'No se localizó UUID en el TimbreFiscalDigital.';
        }

        if (!$data['rfc_emisor']) {
            $errors[] = 'No se localizó el RFC del emisor.';
        }

        if (!$data['rfc_receptor']) {
            $warnings[] = 'No se localizó el RFC del receptor.';
        }

        if ($data['total'] === null) {
            $errors[] = 'No se localizó el total del CFDI.';
        }

        if (!$data['moneda']) {
            $errors[] = 'No se localizó la moneda del CFDI.';
        }

        if (!$data['fecha_emision']) {
            $errors[] = 'No se localizó una fecha de emisión válida.';
        }

        if (!$data['sello_presente']) {
            $errors[] = 'El CFDI no contiene sello digital.';
        }

        if (!$data['certificado_presente']) {
            $errors[] = 'El CFDI no contiene certificado o número de certificado.';
        }

        if (!$data['timbre_presente']) {
            $errors[] = 'El CFDI no contiene TimbreFiscalDigital.';
        }

        if ($data['moneda'] !== 'MXN' && ($data['tipo_cambio'] === null || $data['tipo_cambio'] <= 0)) {
            $warnings[] = 'El CFDI utiliza moneda distinta de MXN y no contiene un tipo de cambio válido.';
        }

        $data['errors'] = array_values(array_unique($errors));
        $data['warnings'] = array_values(array_unique($warnings));
        $data['raw'] = array_filter([
            'version_cfdi' => $data['version_cfdi'],
            'uuid_cfdi' => $data['uuid_cfdi'],
            'rfc_emisor' => $data['rfc_emisor'],
            'nombre_emisor' => $data['nombre_emisor'],
            'rfc_receptor' => $data['rfc_receptor'],
            'fecha_emision' => $data['fecha_emision'],
            'subtotal' => $data['subtotal'],
            'descuento' => $data['descuento'],
            'total' => $data['total'],
            'moneda' => $data['moneda'],
            'tipo_cambio' => $data['tipo_cambio'],
            'tipo_comprobante' => $data['tipo_comprobante'],
            'metodo_pago' => $data['metodo_pago'],
            'forma_pago' => $data['forma_pago'],
            'lugar_expedicion' => $data['lugar_expedicion'],
        ], static fn ($value) => $value !== null && $value !== '');

        return $data;
    }

    public function reconcileValuePayload(string $numeroActivo, array $payload): array
    {
        $validation = $this->latestValidationForAsset($numeroActivo);

        if (!$validation) {
            return [
                'status' => 'sin_xml',
                'details' => [
                    'No existe un XML CFDI técnico vigente para este activo. La captura de valores permanece permitida porque SWAFI no compara los datos del XML contra el registro del activo.',
                ],
                'blockingErrors' => [],
                'validation_id' => null,
            ];
        }

        $status = $validation->estatus_validacion === 'valido'
            ? 'validado'
            : 'observado';
        $details = $validation->estatus_validacion === 'valido'
            ? [
                'Existe un XML CFDI que superó la validación técnica de estructura e integridad. Sus datos se muestran únicamente como referencia documental.',
            ]
            : [
                'El XML CFDI presenta incidencias técnicas de estructura o integridad. Esta condición no bloquea ni compara los valores fiscales o financieros del activo.',
            ];

        return [
            'status' => $status,
            'details' => $details,
            'blockingErrors' => [],
            'validation_id' => $validation->id,
        ];
    }

    public function recalculateExpedienteStatus(int $expedienteId, string $numeroActivo, ?int $userId = null): string
    {
        $documentCounts = DB::table('documentos_expediente')
            ->where('expediente_id', $expedienteId)
            ->where('vigente', true)
            ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento) = 'PDF' THEN 1 ELSE 0 END) as total_pdf")
            ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento) = 'XML' THEN 1 ELSE 0 END) as total_xml")
            ->first();

        $hasPdf = (int) ($documentCounts->total_pdf ?? 0) > 0;
        $hasXml = (int) ($documentCounts->total_xml ?? 0) > 0;
        $status = 'incompleto';

        if ($hasPdf && $hasXml) {
            $activeXmlIds = DB::table('documentos_expediente')
                ->where('expediente_id', $expedienteId)
                ->where('vigente', true)
                ->whereRaw('UPPER(tipo_documento) = ?', ['XML'])
                ->pluck('id');

            $validations = Schema::hasTable('cfdi_validaciones')
                ? DB::table('cfdi_validaciones')->whereIn('documento_id', $activeXmlIds)->get()
                : collect();

            $allValidated = $activeXmlIds->isNotEmpty() && $validations->count() === $activeXmlIds->count();
            $allValid = $allValidated && $validations->every(
                static fn ($validation) => $validation->estatus_validacion === 'valido'
            );

            $hasOpenObservations = Schema::hasTable('expediente_observaciones')
                && DB::table('expediente_observaciones')
                    ->where('expediente_id', $expedienteId)
                    ->whereIn('estatus', ['abierta', 'en_atencion', 'atendida', 'rechazada'])
                    ->exists();

            $status = $allValid && !$hasOpenObservations ? 'completo' : 'observado';
        }

        DB::table('expedientes')
            ->where('id', $expedienteId)
            ->whereNull('deleted_at')
            ->update([
                'estatus' => $status,
                'actualizado_por' => $userId,
                'updated_at' => now(),
            ]);

        $assetStatuses = DB::table('expedientes')
            ->where('numero_activo', $numeroActivo)
            ->whereNull('deleted_at')
            ->pluck('estatus')
            ->all();

        $assetStatus = in_array('observado', $assetStatuses, true)
            ? 'observado'
            : (in_array('incompleto', $assetStatuses, true) ? 'incompleto' : 'completo');

        DB::table('activos')
            ->where('numero_activo', $numeroActivo)
            ->update([
                'estatus_documental' => $assetStatus,
                'actualizado_por' => $userId,
                'updated_at' => now(),
            ]);

        return $status;
    }

    public function latestValidationForAsset(string $numeroActivo): ?CfdiValidacion
    {
        return CfdiValidacion::query()
            ->where('numero_activo', $numeroActivo)
            ->whereHas('documento', function ($query) {
                $query->where('vigente', true)
                    ->whereRaw('UPPER(tipo_documento) = ?', ['XML']);
            })
            ->orderByDesc('validado_at')
            ->orderByDesc('id')
            ->first();
    }


    private function persistInvalidValidation(object $context, ?int $userId, array $errors, array $extracted): CfdiValidacion
    {
        $validation = CfdiValidacion::updateOrCreate(
            ['documento_id' => $context->documento_id],
            [
                'expediente_id' => $context->expediente_id,
                'numero_activo' => $context->numero_activo,
                'xml_bien_formado' => false,
                'sello_presente' => false,
                'certificado_presente' => false,
                'timbre_presente' => false,
                'coincide_uuid' => null,
                'coincide_rfc' => null,
                'coincide_fecha' => null,
                'coincide_monto' => null,
                'coincide_moneda' => null,
                'diferencia_monto' => null,
                'estatus_validacion' => 'invalido',
                'errores' => $errors,
                'advertencias' => null,
                'datos_extraidos' => $extracted,
                'validado_por' => $userId,
                'validado_at' => now(),
            ]
        );

        $this->reconcileStoredValue((string) $context->numero_activo, $validation, $userId);

        $this->registerAudit(
            numeroActivo: (string) $context->numero_activo,
            userId: $userId,
            action: 'VALIDACION_CFDI_FALLIDA',
            table: 'cfdi_validaciones',
            key: (string) $validation->id,
            after: ['errores' => $errors]
        );

        return $validation;
    }

    private function reconcileStoredValue(string $numeroActivo, CfdiValidacion $validation, ?int $userId): void
    {
        if (!Schema::hasColumn('valores_activo', 'conciliacion_cfdi')) {
            return;
        }

        $value = DB::table('valores_activo')
            ->where('numero_activo', $numeroActivo)
            ->whereNull('deleted_at')
            ->first();

        if (!$value) {
            return;
        }

        $result = $this->reconcileValuePayload($numeroActivo, (array) $value);

        DB::table('valores_activo')
            ->where('id', $value->id)
            ->whereNull('deleted_at')
            ->update([
                'cfdi_validacion_id' => $validation->id,
                'conciliacion_cfdi' => $result['status'],
                'conciliacion_detalle' => json_encode($result['details'], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        $this->registerAudit(
            numeroActivo: $numeroActivo,
            userId: $userId,
            action: 'ACTUALIZACION_SOPORTE_XML',
            table: 'valores_activo',
            key: (string) $value->id,
            after: ['estatus' => $result['status'], 'detalle' => $result['details']]
        );
    }

    private function extractWithoutDom(string $xml): array
    {
        $errors = [];
        $warnings = [];

        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            return $this->emptyExtraction([
                'El XML contiene una declaración DOCTYPE o ENTITY no permitida por seguridad.',
            ]);
        }

        if (!preg_match('/<(?:[A-Za-z_][\w.-]*:)?Comprobante\b([^>]*)>/i', $xml, $rootMatch)) {
            return $this->emptyExtraction(['No se localizó el nodo Comprobante del CFDI.']);
        }

        if (!preg_match('/<\/(?:[A-Za-z_][\w.-]*:)?Comprobante\s*>/i', $xml)) {
            return $this->emptyExtraction(['El nodo Comprobante no tiene cierre; el XML no está bien formado.']);
        }

        $root = $this->parseTagAttributes($rootMatch[1]);
        preg_match('/<(?:[A-Za-z_][\w.-]*:)?Emisor\b([^>]*)\/?>/i', $xml, $emisorMatch);
        preg_match('/<(?:[A-Za-z_][\w.-]*:)?Receptor\b([^>]*)\/?>/i', $xml, $receptorMatch);
        preg_match('/<(?:[A-Za-z_][\w.-]*:)?TimbreFiscalDigital\b([^>]*)\/?>/i', $xml, $timbreMatch);
        $emisor = $this->parseTagAttributes($emisorMatch[1] ?? '');
        $receptor = $this->parseTagAttributes($receptorMatch[1] ?? '');
        $timbre = $this->parseTagAttributes($timbreMatch[1] ?? '');
        $get = static function (array $attributes, array $names): ?string {
            foreach ($names as $name) {
                foreach ($attributes as $key => $value) {
                    if (strcasecmp($key, $name) === 0) {
                        return $value === '' ? null : $value;
                    }
                }
            }

            return null;
        };

        $data = [
            'xml_bien_formado' => true,
            'version_cfdi' => $get($root, ['Version', 'version']),
            'uuid_cfdi' => $this->upper($get($timbre, ['UUID'])),
            'rfc_emisor' => $this->upper($get($emisor, ['Rfc', 'RFC'])),
            'nombre_emisor' => $get($emisor, ['Nombre']),
            'rfc_receptor' => $this->upper($get($receptor, ['Rfc', 'RFC'])),
            'fecha_emision' => $this->parseDateTime($get($root, ['Fecha'])),
            'subtotal' => $this->decimal($get($root, ['SubTotal'])),
            'descuento' => $this->decimal($get($root, ['Descuento'])),
            'total' => $this->decimal($get($root, ['Total'])),
            'moneda' => $this->upper($get($root, ['Moneda'])),
            'tipo_cambio' => $this->decimal($get($root, ['TipoCambio']), 6),
            'tipo_comprobante' => $this->upper($get($root, ['TipoDeComprobante'])),
            'metodo_pago' => $this->upper($get($root, ['MetodoPago'])),
            'forma_pago' => $this->upper($get($root, ['FormaPago'])),
            'lugar_expedicion' => $get($root, ['LugarExpedicion']),
            'sello_presente' => $get($root, ['Sello']) !== null,
            'certificado_presente' => $get($root, ['Certificado', 'NoCertificado']) !== null,
            'timbre_presente' => !empty($timbre),
            'errors' => [],
            'warnings' => [],
            'raw' => [],
        ];

        if (!in_array($data['version_cfdi'], ['3.3', '4.0'], true)) {
            $warnings[] = 'La versión CFDI no corresponde a 3.3 o 4.0.';
        }
        if (!$data['uuid_cfdi']) {
            $errors[] = 'No se localizó UUID en el TimbreFiscalDigital.';
        }
        if (!$data['rfc_emisor']) {
            $errors[] = 'No se localizó el RFC del emisor.';
        }
        if (!$data['rfc_receptor']) {
            $warnings[] = 'No se localizó el RFC del receptor.';
        }
        if ($data['total'] === null) {
            $errors[] = 'No se localizó el total del CFDI.';
        }
        if (!$data['moneda']) {
            $errors[] = 'No se localizó la moneda del CFDI.';
        }
        if (!$data['fecha_emision']) {
            $errors[] = 'No se localizó una fecha de emisión válida.';
        }
        if (!$data['sello_presente']) {
            $errors[] = 'El CFDI no contiene sello digital.';
        }
        if (!$data['certificado_presente']) {
            $errors[] = 'El CFDI no contiene certificado o número de certificado.';
        }
        if (!$data['timbre_presente']) {
            $errors[] = 'El CFDI no contiene TimbreFiscalDigital.';
        }
        if ($data['moneda'] !== 'MXN' && ($data['tipo_cambio'] === null || $data['tipo_cambio'] <= 0)) {
            $warnings[] = 'El CFDI utiliza moneda distinta de MXN y no contiene tipo de cambio válido.';
        }

        $data['errors'] = array_values(array_unique($errors));
        $data['warnings'] = array_values(array_unique($warnings));
        $data['raw'] = array_filter([
            'version_cfdi' => $data['version_cfdi'],
            'uuid_cfdi' => $data['uuid_cfdi'],
            'rfc_emisor' => $data['rfc_emisor'],
            'nombre_emisor' => $data['nombre_emisor'],
            'rfc_receptor' => $data['rfc_receptor'],
            'fecha_emision' => $data['fecha_emision'],
            'subtotal' => $data['subtotal'],
            'descuento' => $data['descuento'],
            'total' => $data['total'],
            'moneda' => $data['moneda'],
            'tipo_cambio' => $data['tipo_cambio'],
            'tipo_comprobante' => $data['tipo_comprobante'],
            'metodo_pago' => $data['metodo_pago'],
            'forma_pago' => $data['forma_pago'],
            'lugar_expedicion' => $data['lugar_expedicion'],
        ], static fn ($value) => $value !== null && $value !== '');

        return $data;
    }

    private function parseTagAttributes(string $source): array
    {
        $attributes = [];
        preg_match_all(
            '/([A-Za-z_][\w:.-]*)\s*=\s*("([^"]*)"|\'([^\']*)\')/u',
            $source,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $raw = $match[3] !== '' ? $match[3] : ($match[4] ?? '');
            $attributes[$match[1]] = strtr($raw, [
                '&amp;' => '&',
                '&lt;' => '<',
                '&gt;' => '>',
                '&quot;' => '"',
                '&apos;' => "'",
            ]);
        }

        return $attributes;
    }

    private function emptyExtraction(array $errors): array
    {
        return [
            'xml_bien_formado' => false,
            'version_cfdi' => null,
            'uuid_cfdi' => null,
            'rfc_emisor' => null,
            'nombre_emisor' => null,
            'rfc_receptor' => null,
            'fecha_emision' => null,
            'subtotal' => null,
            'descuento' => null,
            'total' => null,
            'moneda' => null,
            'tipo_cambio' => null,
            'tipo_comprobante' => null,
            'metodo_pago' => null,
            'forma_pago' => null,
            'lugar_expedicion' => null,
            'sello_presente' => false,
            'certificado_presente' => false,
            'timbre_presente' => false,
            'errors' => $errors,
            'warnings' => [],
            'raw' => [],
        ];
    }

    private function attribute(?\DOMNode $node, array $names): ?string
    {
        if (!$node instanceof DOMElement) {
            return null;
        }

        foreach ($names as $name) {
            if ($node->hasAttribute($name)) {
                $value = trim($node->getAttribute($name));

                return $value === '' ? null : $value;
            }
        }

        return null;
    }

    private function filledAttribute(?\DOMNode $node, array $names): bool
    {
        return $this->attribute($node, $names) !== null;
    }

    private function decimal(?string $value, int $scale = 2): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return round((float) $value, $scale);
    }

    private function parseDateTime(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $exception) {
            app(SafeExceptionReporter::class)->warning(
                $exception,
                'cfdi_date_normalization',
                ['value_length' => mb_strlen((string) $value)]
            );

            return null;
        }
    }

    private function upper(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_strtoupper($value, 'UTF-8');
    }

    private function registerAudit(
        string $numeroActivo,
        ?int $userId,
        string $action,
        string $table,
        string $key,
        array $after
    ): void {
        if (!Schema::hasTable('bitacora_auditoria')) {
            return;
        }

        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => $numeroActivo,
            'user_id' => $userId,
            'modulo' => 'M01 Expedientes / M02 Control fiscal',
            'accion' => mb_substr($action, 0, 40),
            'tabla_afectada' => $table,
            'registro_clave' => $key,
            'antes' => null,
            'despues' => json_encode($after, JSON_UNESCAPED_UNICODE),
            'ip' => request()?->ip(),
            'fecha_evento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
