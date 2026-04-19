<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentoExpediente extends Model
{
    protected $table = 'documentos_expediente';

    protected $fillable = [
        'expediente_id',
        'tipo_documento',
        'nombre_archivo',
        'ruta_archivo',
        'mime_type',
        'tamano_bytes',
        'hash_sha256',
        'version',
        'vigente',
        'cargado_por',
    ];

    public function expediente()
    {
        return $this->belongsTo(Expediente::class, 'expediente_id');
    }
}
