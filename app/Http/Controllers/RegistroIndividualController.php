<?php

namespace App\Http\Controllers;

use App\Http\Requests\LookupExistingAssetRequest;
use App\Http\Requests\SearchExistingAssetsRequest;
use App\Http\Requests\StoreRegistroIndividualRequest;
use App\Models\DocumentoExpediente;
use App\Models\Expediente;
use App\Services\AssetRegistrationService;
use App\Services\AssetStatusCatalogService;
use App\Services\FinancialCatalogService;
use App\Services\SwafiStorageService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RegistroIndividualController extends Controller
{
    public function __construct(
        private readonly SwafiStorageService $storage,
        private readonly AssetStatusCatalogService $statusCatalogs,
        private readonly AssetRegistrationService $assets,
        private readonly FinancialCatalogService $financialCatalogs
    ) {
    }

    public function create()
    {
        return view('swafi.registro-individual', [
            'tiposActivo' => $this->catalogOptions('tipos_activo'),
            'proveedores' => $this->catalogOptions('proveedores'),
            'centrosCosto' => $this->catalogOptions('centros_costo'),
            'plantas' => $this->catalogOptions('plantas'),
            'ubicaciones' => $this->catalogOptions('ubicaciones'),
            'responsables' => $this->catalogOptions('responsables'),
            'estatusOperativos' => $this->statusCatalogs->operationalOptions(),
            'monedas' => $this->financialCatalogs->currencies(),
        ]);
    }

    public function showExistingAsset(LookupExistingAssetRequest $request): JsonResponse
    {
        $data = $request->validated();
        $asset = $this->assets->lookupActive((string) $data['numero_activo']);

        if (!$asset) {
            return response()->json([
                'message' => 'No se encontró un activo vigente con ese número.',
            ], 404);
        }

        return response()->json([
            'data' => $asset,
        ]);
    }

    public function searchExistingAssets(SearchExistingAssetsRequest $request): JsonResponse
    {
        return response()->json(
            $this->assets->searchActive($request->validated())
        );
    }

    public function store(StoreRegistroIndividualRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $storedFiles = [];

        try {
            DB::transaction(function () use ($data, $request, &$storedFiles): void {
                $userId = auth()->id();
                $assetMode = (string) $data['asset_mode'];
                $assetWasCreated = $assetMode === 'new';

                if ($assetWasCreated) {
                    $asset = $this->assets->createNew(
                        data: $data,
                        estatusDocumental: 'incompleto',
                        userId: $userId
                    );
                } else {
                    $asset = $this->assets->findActiveForUpdate((string) $data['numero_activo']);

                    if (!$asset) {
                        throw ValidationException::withMessages([
                            'numero_activo' => 'El activo seleccionado dejó de estar disponible. Búscalo nuevamente antes de guardar el expediente.',
                        ]);
                    }
                }

                $documentos = $this->prepareUploadedDocuments(
                    request: $request,
                    numeroActivo: $asset->numero_activo,
                    folioFactura: (string) $data['folio_factura'],
                    storedFiles: $storedFiles
                );
                $estatusDocumental = $this->resolveDocumentStatus($documentos);

                if ($assetWasCreated) {
                    $asset->forceFill([
                        'estatus_documental' => $estatusDocumental,
                        'actualizado_por' => $userId,
                    ])->save();
                }

                $expediente = Expediente::create([
                    'numero_activo' => $asset->numero_activo,
                    'folio_factura' => $data['folio_factura'],
                    'uuid_cfdi' => $data['uuid_cfdi'] ?? null,
                    'fecha_factura' => $data['fecha_factura'],
                    'monto_factura' => $data['monto_factura'],
                    'moneda' => $data['moneda'],
                    'estatus' => $estatusDocumental,
                    'observaciones' => $data['observaciones'] ?? null,
                    'creado_por' => $userId,
                    'actualizado_por' => $userId,
                ]);

                foreach ($documentos as $doc) {
                    DocumentoExpediente::create([
                        'expediente_id' => $expediente->id,
                        'tipo_documento' => $doc['tipo_documento'],
                        'nombre_archivo' => $doc['nombre_archivo'],
                        'ruta_archivo' => $doc['ruta_archivo'],
                        'storage_disk' => $doc['storage_disk'],
                        'mime_type' => $doc['mime_type'],
                        'tamano_bytes' => $doc['tamano_bytes'],
                        'hash_sha256' => $doc['hash_sha256'],
                        'version' => 1,
                        'vigente' => true,
                        'cargado_por' => $userId,
                    ]);
                }

                $this->registerAudit(
                    numeroActivo: $asset->numero_activo,
                    expedienteId: (int) $expediente->id,
                    assetMode: $assetMode,
                    documentStatus: $estatusDocumental,
                    documentCount: count($documentos),
                    userId: $userId
                );
            });
        } catch (QueryException $exception) {
            $this->cleanupStoredFiles($storedFiles);

            if ((string) $exception->getCode() === '23000') {
                throw ValidationException::withMessages([
                    'registro' => 'El activo o la combinación activo/folio fue registrada simultáneamente por otra operación. Actualiza la página y verifica la información antes de intentarlo nuevamente.',
                ]);
            }

            throw $exception;
        } catch (Throwable $exception) {
            $this->cleanupStoredFiles($storedFiles);

            throw $exception;
        }

        $message = $data['asset_mode'] === 'existing'
            ? 'El expediente se guardó y se asoció al activo existente sin modificar sus datos maestros.'
            : 'El activo y su expediente se guardaron correctamente con soporte documental persistente.';

        return redirect()
            ->route('registro-individual')
            ->with('success', $message);
    }

    private function prepareUploadedDocuments(
        StoreRegistroIndividualRequest $request,
        string $numeroActivo,
        string $folioFactura,
        array &$storedFiles
    ): array {
        if (!$request->hasFile('documentos')) {
            return [];
        }

        $docs = [];
        $assetPath = Str::slug($numeroActivo ?: 'activo-sin-numero');
        $invoicePath = Str::slug($folioFactura ?: 'factura-sin-folio');
        $basePath = "swafi/expedientes/{$assetPath}/{$invoicePath}";

        foreach ($request->file('documentos') as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $extension = strtolower($file->getClientOriginalExtension());
            $tipoDocumento = match ($extension) {
                'pdf' => 'PDF',
                'xml' => 'XML',
                default => 'OTRO',
            };

            $originalName = $file->getClientOriginalName();
            $safeBaseName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'documento';
            $storedName = now()->format('YmdHis')
                . '_'
                . Str::random(8)
                . '_'
                . $safeBaseName
                . '.'
                . $extension;

            $stored = $this->storage->storeUploadedFile(
                file: $file,
                directory: $basePath,
                storedName: $storedName
            );

            $storedFiles[] = $stored;

            $docs[] = [
                'tipo_documento' => $tipoDocumento,
                'nombre_archivo' => $originalName,
                'ruta_archivo' => $stored['path'],
                'storage_disk' => $stored['disk'],
                'mime_type' => $stored['mime_type'],
                'tamano_bytes' => $stored['tamano_bytes'],
                'hash_sha256' => $stored['hash_sha256'],
            ];
        }

        return $docs;
    }

    private function resolveDocumentStatus(array $docs): string
    {
        $tipos = collect($docs)
            ->pluck('tipo_documento')
            ->unique()
            ->values()
            ->all();

        return in_array('PDF', $tipos, true) && in_array('XML', $tipos, true)
            ? 'completo'
            : 'incompleto';
    }

    private function registerAudit(
        string $numeroActivo,
        int $expedienteId,
        string $assetMode,
        string $documentStatus,
        int $documentCount,
        ?int $userId
    ): void {
        DB::table('bitacora_auditoria')->insert([
            'numero_activo' => $numeroActivo,
            'user_id' => $userId,
            'modulo' => 'M01 Gestión de expedientes',
            'accion' => 'ALTA_EXPEDIENTE_INDIVIDUAL',
            'tabla_afectada' => 'expedientes,documentos_expediente',
            'registro_clave' => (string) $expedienteId,
            'antes' => null,
            'despues' => json_encode([
                'modo_activo' => $assetMode,
                'activo_creado' => $assetMode === 'new',
                'expediente_id' => $expedienteId,
                'estatus_documental' => $documentStatus,
                'documentos_registrados' => $documentCount,
                'datos_maestros_activo_modificados' => false,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip' => request()->ip(),
            'fecha_evento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function cleanupStoredFiles(array $storedFiles): void
    {
        foreach ($storedFiles as $storedFile) {
            $this->storage->delete($storedFile['disk'], $storedFile['path']);
        }
    }

    private function catalogOptions(string $table)
    {
        if (!Schema::hasTable($table)) {
            return collect();
        }

        $rows = DB::table($table)
            ->when(Schema::hasColumn($table, 'estatus'), function ($query): void {
                $query->where('estatus', 'activo');
            })
            ->get();

        return $rows->map(function ($row): object {
            $data = (array) $row;

            $label = $data['nombre']
                ?? $data['descripcion']
                ?? $data['clave']
                ?? $data['codigo']
                ?? $data['rfc']
                ?? ('Registro ' . ($data['id'] ?? ''));

            if (isset($data['rfc'], $data['nombre'])) {
                $label = $data['nombre'] . ' (' . $data['rfc'] . ')';
            }

            return (object) [
                'id' => $data['id'] ?? null,
                'label' => $label,
            ];
        });
    }
}
