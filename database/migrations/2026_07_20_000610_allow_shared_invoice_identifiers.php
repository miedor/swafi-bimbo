<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'expedientes_uuid_cfdi_unique';

    private const NON_UNIQUE_INDEX = 'expedientes_uuid_cfdi_idx';

    private const AUDIT_ACTION = 'HABILITA_FACTURA_COMPARTIDA_MULTIACTIVO';

    /** @var array<int, string> */
    private const BUSINESS_ERRORS = [
        'El UUID del XML no coincide con el UUID registrado en el expediente.',
        'El RFC del emisor del XML no coincide con el RFC del proveedor asignado al activo.',
        'El total del XML no coincide con el monto de la factura registrado en el expediente.',
        'La moneda del XML no coincide con la moneda registrada en el expediente.',
        'El UUID extraído ya pertenece a otro expediente de SWAFI.',
    ];

    /** @var array<int, string> */
    private const BUSINESS_WARNINGS = [
        'La fecha del XML no coincide con la fecha de factura registrada.',
    ];

    public function up(): void
    {
        $this->replaceUuidUniqueIndex();
        $this->removeLegacyBusinessComparisons();
        $this->normalizeStoredXmlSupportStatus();
        $this->updatePermissionDescription();
        $this->registerAuditEvent();
    }

    public function down(): void
    {
        if (!Schema::hasTable('expedientes') || !Schema::hasColumn('expedientes', 'uuid_cfdi')) {
            return;
        }

        $hasDuplicates = DB::table('expedientes')
            ->whereNotNull('uuid_cfdi')
            ->where('uuid_cfdi', '<>', '')
            ->select('uuid_cfdi')
            ->groupBy('uuid_cfdi')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new \RuntimeException(
                'No es posible restaurar la restricción única de UUID porque existen facturas compartidas entre varios activos.'
            );
        }

        if ($this->indexExists('expedientes', self::NON_UNIQUE_INDEX)) {
            Schema::table('expedientes', function (Blueprint $table): void {
                $table->dropIndex(self::NON_UNIQUE_INDEX);
            });
        }

        if (!$this->indexExists('expedientes', self::UNIQUE_INDEX)) {
            Schema::table('expedientes', function (Blueprint $table): void {
                $table->unique('uuid_cfdi', self::UNIQUE_INDEX);
            });
        }

        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')
                ->where('accion', self::AUDIT_ACTION)
                ->where('registro_clave', 'FACTURA_COMPARTIDA_MULTIACTIVO')
                ->delete();
        }
    }

    private function replaceUuidUniqueIndex(): void
    {
        if (!Schema::hasTable('expedientes') || !Schema::hasColumn('expedientes', 'uuid_cfdi')) {
            return;
        }

        if ($this->indexExists('expedientes', self::UNIQUE_INDEX)) {
            Schema::table('expedientes', function (Blueprint $table): void {
                $table->dropUnique(self::UNIQUE_INDEX);
            });
        }

        if (!$this->indexExists('expedientes', self::NON_UNIQUE_INDEX)) {
            Schema::table('expedientes', function (Blueprint $table): void {
                $table->index('uuid_cfdi', self::NON_UNIQUE_INDEX);
            });
        }
    }

    private function removeLegacyBusinessComparisons(): void
    {
        if (!Schema::hasTable('cfdi_validaciones')) {
            return;
        }

        DB::table('cfdi_validaciones')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $errors = $this->withoutMessages(
                        $this->decodeArray($row->errores ?? null),
                        self::BUSINESS_ERRORS
                    );
                    $warnings = $this->withoutMessages(
                        $this->decodeArray($row->advertencias ?? null),
                        self::BUSINESS_WARNINGS
                    );
                    $data = $this->decodeAssociative($row->datos_extraidos ?? null);
                    $information = $this->decodeStringList($data['informacion'] ?? []);
                    $information = array_values(array_filter(
                        $information,
                        static fn (string $message): bool => !str_contains(
                            $message,
                            'completó automáticamente el UUID'
                        )
                    ));
                    $information[] = 'Los datos extraídos del XML se conservan como referencia documental y no se comparan contra los registros del activo.';
                    $data['informacion'] = array_values(array_unique($information));

                    $status = !(bool) ($row->xml_bien_formado ?? false) || $errors !== []
                        ? 'invalido'
                        : ($warnings !== [] ? 'observado' : 'valido');

                    DB::table('cfdi_validaciones')
                        ->where('id', $row->id)
                        ->update([
                            'coincide_uuid' => null,
                            'coincide_rfc' => null,
                            'coincide_fecha' => null,
                            'coincide_monto' => null,
                            'coincide_moneda' => null,
                            'diferencia_monto' => null,
                            'estatus_validacion' => $status,
                            'errores' => $errors === []
                                ? null
                                : json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                            'advertencias' => $warnings === []
                                ? null
                                : json_encode($warnings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                            'datos_extraidos' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    private function normalizeStoredXmlSupportStatus(): void
    {
        if (!Schema::hasTable('valores_activo')) {
            return;
        }

        DB::table('valores_activo')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                $validationStatuses = collect();

                if (Schema::hasTable('cfdi_validaciones')) {
                    $validationIds = collect($rows)
                        ->pluck('cfdi_validacion_id')
                        ->filter()
                        ->unique()
                        ->values();

                    if ($validationIds->isNotEmpty()) {
                        $validationStatuses = DB::table('cfdi_validaciones')
                            ->whereIn('id', $validationIds)
                            ->pluck('estatus_validacion', 'id');
                    }
                }

                foreach ($rows as $row) {
                    $validationStatus = !empty($row->cfdi_validacion_id)
                        ? $validationStatuses->get($row->cfdi_validacion_id)
                        : null;
                    $status = $validationStatus === null
                        ? 'sin_xml'
                        : ($validationStatus === 'valido' ? 'validado' : 'observado');
                    $details = $validationStatus === null
                        ? ['No existe un XML CFDI técnico asociado. Los valores del activo permanecen independientes.']
                        : ($status === 'validado'
                            ? ['El XML CFDI asociado superó la validación técnica. Sus datos se conservan únicamente como referencia documental.']
                            : ['El XML CFDI asociado presenta incidencias técnicas. Esto no bloquea ni compara los valores del activo.']);

                    DB::table('valores_activo')
                        ->where('id', $row->id)
                        ->update([
                            'conciliacion_cfdi' => $status,
                            'conciliacion_detalle' => json_encode(
                                $details,
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                            ),
                            'updated_at' => now(),
                        ]);
                }
            });
    }


    private function updatePermissionDescription(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')
            ->where('clave', 'cfdi.validar')
            ->update([
                'descripcion' => 'Ejecutar la validación técnica de estructura, integridad y seguridad del XML CFDI, sin comparar sus datos contra los registros del activo.',
                'updated_at' => now(),
            ]);
    }

    private function registerAuditEvent(): void
    {
        if (!Schema::hasTable('bitacora_auditoria')) {
            return;
        }

        DB::table('bitacora_auditoria')->updateOrInsert(
            [
                'accion' => self::AUDIT_ACTION,
                'registro_clave' => 'FACTURA_COMPARTIDA_MULTIACTIVO',
            ],
            [
                'numero_activo' => null,
                'user_id' => null,
                'modulo' => 'M01 Gestión de expedientes de activo fijo',
                'tabla_afectada' => 'expedientes,cfdi_validaciones,valores_activo',
                'antes' => json_encode([
                    'uuid_cfdi_unico_global' => true,
                    'comparacion_xml_con_activo' => true,
                    'comparacion_xml_con_valores' => true,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'despues' => json_encode([
                    'uuid_cfdi_compartido_entre_activos' => true,
                    'validacion_xml' => 'estructura, integridad y seguridad',
                    'datos_extraidos' => 'referencia documental sin conciliacion de negocio',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'ip' => null,
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /** @return array<int, string> */
    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_string'));
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded)
            ? array_values(array_filter($decoded, 'is_string'))
            : [];
    }

    /** @return array<string, mixed> */
    private function decodeAssociative(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<int, string> */
    private function decodeStringList(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        return is_array($value)
            ? array_values(array_filter($value, 'is_string'))
            : [];
    }

    /** @param array<int, string> $messages @param array<int, string> $removed */
    private function withoutMessages(array $messages, array $removed): array
    {
        return array_values(array_filter(
            $messages,
            static fn (string $message): bool => !in_array($message, $removed, true)
        ));
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(static fn (array $index): bool => ($index['name'] ?? null) === $indexName);
    }
};
