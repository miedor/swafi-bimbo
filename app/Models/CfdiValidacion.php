<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CfdiValidacion extends Model
{
    protected $table = 'cfdi_validaciones';

    protected $fillable = [
        'expediente_id',
        'documento_id',
        'numero_activo',
        'version_cfdi',
        'uuid_cfdi',
        'rfc_emisor',
        'nombre_emisor',
        'rfc_receptor',
        'fecha_emision',
        'subtotal',
        'descuento',
        'total',
        'moneda',
        'tipo_cambio',
        'tipo_comprobante',
        'metodo_pago',
        'forma_pago',
        'lugar_expedicion',
        'xml_bien_formado',
        'sello_presente',
        'certificado_presente',
        'timbre_presente',
        'coincide_uuid',
        'coincide_rfc',
        'coincide_fecha',
        'coincide_monto',
        'coincide_moneda',
        'diferencia_monto',
        'estatus_validacion',
        'errores',
        'advertencias',
        'datos_extraidos',
        'validado_por',
        'validado_at',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
        'tipo_cambio' => 'decimal:6',
        'xml_bien_formado' => 'boolean',
        'sello_presente' => 'boolean',
        'certificado_presente' => 'boolean',
        'timbre_presente' => 'boolean',
        'coincide_uuid' => 'boolean',
        'coincide_rfc' => 'boolean',
        'coincide_fecha' => 'boolean',
        'coincide_monto' => 'boolean',
        'coincide_moneda' => 'boolean',
        'diferencia_monto' => 'decimal:2',
        'errores' => 'array',
        'advertencias' => 'array',
        'datos_extraidos' => 'array',
        'validado_at' => 'datetime',
    ];

    public function expediente()
    {
        return $this->belongsTo(Expediente::class, 'expediente_id');
    }

    public function documento()
    {
        return $this->belongsTo(DocumentoExpediente::class, 'documento_id');
    }

    public function validador()
    {
        return $this->belongsTo(User::class, 'validado_por');
    }
}
