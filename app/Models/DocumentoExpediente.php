<?php

namespace App\Models;

use App\Services\CfdiValidationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DocumentoExpediente extends Model
{
    protected $table = 'documentos_expediente';

    protected $fillable = [
        'expediente_id',
        'tipo_documento',
        'nombre_archivo',
        'ruta_archivo',
        'storage_disk',
        'mime_type',
        'tamano_bytes',
        'hash_sha256',
        'version',
        'vigente',
        'cargado_por',
    ];

    protected $casts = [
        'vigente' => 'boolean',
        'tamano_bytes' => 'integer',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::created(function (DocumentoExpediente $document): void {
            static::scheduleDocumentValidation($document);
        });

        static::updated(function (DocumentoExpediente $document): void {
            if ($document->wasChanged(['ruta_archivo', 'hash_sha256', 'vigente', 'tipo_documento'])) {
                static::scheduleDocumentValidation($document);
            }
        });
    }

    public function expediente()
    {
        return $this->belongsTo(Expediente::class, 'expediente_id');
    }

    public function cfdiValidacion()
    {
        return $this->hasOne(CfdiValidacion::class, 'documento_id');
    }

    private static function scheduleDocumentValidation(DocumentoExpediente $document): void
    {
        $documentId = (int) $document->id;
        $expedienteId = (int) $document->expediente_id;
        $tipo = strtoupper((string) $document->tipo_documento);
        $userId = auth()->id();

        DB::afterCommit(function () use ($documentId, $expedienteId, $tipo, $userId): void {
            try {
                $fresh = DocumentoExpediente::find($documentId);
                $expediente = DB::table('expedientes')->where('id', $expedienteId)->first();

                if (!$expediente) {
                    return;
                }

                $service = app(CfdiValidationService::class);

                if (
                    $fresh &&
                    $fresh->vigente &&
                    $tipo === 'XML' &&
                    Schema::hasTable('cfdi_validaciones')
                ) {
                    $service->validateDocument($fresh, $userId);

                    return;
                }

                $service->recalculateExpedienteStatus(
                    $expedienteId,
                    (string) $expediente->numero_activo,
                    $userId
                );
            } catch (\Throwable $exception) {
                report($exception);
            }
        });
    }
}
