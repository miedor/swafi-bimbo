<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const AUDIT_ACTION = 'HABILITA_DEPRECIACION_REFERENCIAL';

    public function up(): void
    {
        $this->createFinancialCatalogs();
        $this->seedFinancialCatalogs();
        $this->normalizeExistingCodes();
        $this->extendAssetValues();
        $this->addCatalogForeignKeys();
        $this->registerAuditEvent();
    }

    public function down(): void
    {
        if (Schema::hasTable('expedientes')) {
            Schema::table('expedientes', function (Blueprint $table): void {
                $table->dropForeign('expedientes_moneda_catalog_fk');
            });
        }

        if (Schema::hasTable('valores_activo')) {
            Schema::table('valores_activo', function (Blueprint $table): void {
                $table->dropForeign('valores_activo_moneda_catalog_fk');
                $table->dropForeign('valores_activo_estatus_contable_catalog_fk');
                $table->dropIndex('valores_activo_depreciacion_ref_idx');
                $table->dropColumn([
                    'metodo_depreciacion',
                    'fecha_inicio_depreciacion',
                    'valor_residual',
                    'depreciacion_estimada',
                    'valor_en_libros_estimado',
                    'calculo_depreciacion_at',
                ]);
            });
        }

        if (Schema::hasTable('bitacora_auditoria')) {
            DB::table('bitacora_auditoria')
                ->where('accion', self::AUDIT_ACTION)
                ->where('registro_clave', 'HU-036-HU-037')
                ->delete();
        }

        Schema::dropIfExists('estatus_contables');
        Schema::dropIfExists('monedas');
    }

    private function createFinancialCatalogs(): void
    {
        if (!Schema::hasTable('monedas')) {
            Schema::create('monedas', function (Blueprint $table): void {
                $table->id();
                $table->string('clave', 10)->unique();
                $table->string('nombre', 100);
                $table->string('simbolo', 10)->nullable();
                $table->unsignedTinyInteger('decimales')->default(2);
                $table->boolean('requiere_tipo_cambio')->default(true);
                $table->boolean('es_sistema')->default(false);
                $table->string('estatus', 20)->default('activo');
                $table->timestamps();

                $table->index(['estatus', 'nombre'], 'monedas_estatus_nombre_idx');
            });
        }

        if (!Schema::hasTable('estatus_contables')) {
            Schema::create('estatus_contables', function (Blueprint $table): void {
                $table->id();
                $table->string('clave', 30)->unique();
                $table->string('nombre', 80)->unique();
                $table->string('descripcion', 255)->nullable();
                $table->unsignedSmallInteger('orden')->default(100);
                $table->boolean('es_sistema')->default(false);
                $table->string('estatus', 20)->default('activo');
                $table->timestamps();

                $table->index(['estatus', 'orden'], 'estatus_contables_estado_orden_idx');
            });
        }
    }

    private function seedFinancialCatalogs(): void
    {
        $now = now();
        $currencies = [
            [
                'clave' => 'MXN',
                'nombre' => 'Peso mexicano',
                'simbolo' => '$',
                'decimales' => 2,
                'requiere_tipo_cambio' => false,
                'es_sistema' => true,
            ],
            [
                'clave' => 'USD',
                'nombre' => 'Dólar estadounidense',
                'simbolo' => 'US$',
                'decimales' => 2,
                'requiere_tipo_cambio' => true,
                'es_sistema' => true,
            ],
            [
                'clave' => 'EUR',
                'nombre' => 'Euro',
                'simbolo' => '€',
                'decimales' => 2,
                'requiere_tipo_cambio' => true,
                'es_sistema' => true,
            ],
        ];

        foreach ($this->existingCurrencyCodes() as $currencyCode) {
            if (collect($currencies)->contains('clave', $currencyCode)) {
                continue;
            }

            $currencies[] = [
                'clave' => $currencyCode,
                'nombre' => $currencyCode,
                'simbolo' => null,
                'decimales' => 2,
                'requiere_tipo_cambio' => $currencyCode !== 'MXN',
                'es_sistema' => false,
            ];
        }

        foreach ($currencies as $currency) {
            DB::table('monedas')->updateOrInsert(
                ['clave' => $currency['clave']],
                array_merge($currency, [
                    'estatus' => 'activo',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        $statuses = [
            [
                'clave' => 'vigente',
                'nombre' => 'Vigente',
                'descripcion' => 'El activo mantiene valores vigentes para consulta y seguimiento.',
                'orden' => 10,
                'es_sistema' => true,
            ],
            [
                'clave' => 'en_revision',
                'nombre' => 'En revisión',
                'descripcion' => 'Los valores requieren validación o conciliación antes de considerarse definitivos.',
                'orden' => 20,
                'es_sistema' => true,
            ],
            [
                'clave' => 'baja',
                'nombre' => 'Baja',
                'descripcion' => 'El activo se encuentra dado de baja para efectos de la consulta contable en SWAFI.',
                'orden' => 30,
                'es_sistema' => true,
            ],
        ];

        foreach ($this->existingAccountingStatuses() as $statusKey) {
            if (collect($statuses)->contains('clave', $statusKey)) {
                continue;
            }

            $statuses[] = [
                'clave' => $statusKey,
                'nombre' => Str::headline($statusKey),
                'descripcion' => 'Estatus migrado desde registros existentes de SWAFI.',
                'orden' => 100,
                'es_sistema' => false,
            ];
        }

        foreach ($statuses as $status) {
            DB::table('estatus_contables')->updateOrInsert(
                ['clave' => $status['clave']],
                array_merge($status, [
                    'estatus' => 'activo',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }

    private function normalizeExistingCodes(): void
    {
        foreach (['valores_activo', 'expedientes'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'moneda')) {
                continue;
            }

            DB::table($table)
                ->whereNull('moneda')
                ->orWhere('moneda', '')
                ->update(['moneda' => 'MXN']);

            DB::table($table)->update([
                'moneda' => DB::raw('UPPER(TRIM(moneda))'),
            ]);

            DB::table($table)
                ->select('id', 'moneda')
                ->orderBy('id')
                ->chunkById(500, function ($records) use ($table): void {
                    foreach ($records as $record) {
                        $currency = (string) ($record->moneda ?? '');

                        if (preg_match('/^[A-Z]{3}$/', $currency) === 1) {
                            continue;
                        }

                        DB::table($table)
                            ->where('id', $record->id)
                            ->update(['moneda' => 'MXN']);
                    }
                });
        }

        if (Schema::hasTable('valores_activo') && Schema::hasColumn('valores_activo', 'estatus_contable')) {
            $records = DB::table('valores_activo')
                ->select('id', 'estatus_contable')
                ->get();

            foreach ($records as $record) {
                $normalized = $this->normalizeStatusKey((string) ($record->estatus_contable ?? 'vigente'));

                DB::table('valores_activo')
                    ->where('id', $record->id)
                    ->update(['estatus_contable' => $normalized]);
            }
        }
    }

    private function extendAssetValues(): void
    {
        if (!Schema::hasTable('valores_activo')) {
            return;
        }

        Schema::table('valores_activo', function (Blueprint $table): void {
            $table->string('metodo_depreciacion', 30)
                ->nullable()
                ->after('vida_util_meses');
            $table->date('fecha_inicio_depreciacion')
                ->nullable()
                ->after('metodo_depreciacion');
            $table->decimal('valor_residual', 18, 2)
                ->default(0)
                ->after('fecha_inicio_depreciacion');
            $table->decimal('depreciacion_estimada', 18, 2)
                ->nullable()
                ->after('valor_residual');
            $table->decimal('valor_en_libros_estimado', 18, 2)
                ->nullable()
                ->after('depreciacion_estimada');
            $table->timestamp('calculo_depreciacion_at')
                ->nullable()
                ->after('valor_en_libros_estimado');

            $table->index(
                ['metodo_depreciacion', 'fecha_inicio_depreciacion', 'fecha_corte'],
                'valores_activo_depreciacion_ref_idx'
            );
        });
    }

    private function addCatalogForeignKeys(): void
    {
        if (Schema::hasTable('valores_activo')) {
            Schema::table('valores_activo', function (Blueprint $table): void {
                $table->foreign('moneda', 'valores_activo_moneda_catalog_fk')
                    ->references('clave')
                    ->on('monedas')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                $table->foreign('estatus_contable', 'valores_activo_estatus_contable_catalog_fk')
                    ->references('clave')
                    ->on('estatus_contables')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            });
        }

        if (Schema::hasTable('expedientes')) {
            Schema::table('expedientes', function (Blueprint $table): void {
                $table->foreign('moneda', 'expedientes_moneda_catalog_fk')
                    ->references('clave')
                    ->on('monedas')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            });
        }
    }

    /**
     * @return array<int, string>
     */
    private function existingCurrencyCodes(): array
    {
        $codes = collect();

        foreach (['valores_activo', 'expedientes'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'moneda')) {
                continue;
            }

            $codes = $codes->merge(
                DB::table($table)
                    ->whereNotNull('moneda')
                    ->where('moneda', '<>', '')
                    ->distinct()
                    ->pluck('moneda')
            );
        }

        return $codes
            ->map(fn (mixed $code): string => mb_strtoupper(trim((string) $code), 'UTF-8'))
            ->filter(fn (string $code): bool => preg_match('/^[A-Z]{3}$/', $code) === 1)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function existingAccountingStatuses(): array
    {
        if (!Schema::hasTable('valores_activo') || !Schema::hasColumn('valores_activo', 'estatus_contable')) {
            return [];
        }

        return DB::table('valores_activo')
            ->whereNotNull('estatus_contable')
            ->where('estatus_contable', '<>', '')
            ->distinct()
            ->pluck('estatus_contable')
            ->map(fn (mixed $status): string => $this->normalizeStatusKey((string) $status))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeStatusKey(string $status): string
    {
        $status = mb_strtolower(trim($status), 'UTF-8');
        $status = Str::ascii($status);
        $status = preg_replace('/[^a-z0-9]+/', '_', $status) ?? '';
        $status = trim($status, '_');

        return $status !== '' ? mb_substr($status, 0, 30) : 'vigente';
    }

    private function registerAuditEvent(): void
    {
        if (!Schema::hasTable('bitacora_auditoria')) {
            return;
        }

        DB::table('bitacora_auditoria')->updateOrInsert(
            [
                'accion' => self::AUDIT_ACTION,
                'registro_clave' => 'HU-036-HU-037',
            ],
            [
                'numero_activo' => null,
                'user_id' => null,
                'modulo' => 'M02 Control fiscal, financiero y ubicación física',
                'tabla_afectada' => 'monedas,estatus_contables,valores_activo',
                'antes' => null,
                'despues' => json_encode([
                    'historias_usuario' => ['HU-036', 'HU-037'],
                    'catalogos_financieros' => ['monedas', 'estatus_contables'],
                    'metodo_inicial' => 'linea_recta',
                    'calculo_referencial' => true,
                    'no_sustituye_erp' => true,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'ip' => null,
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
};
