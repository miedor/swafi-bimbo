<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegistroIndividualRequest;
use App\Models\Activo;
use App\Models\DocumentoExpediente;
use App\Models\Expediente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RegistroIndividualController extends Controller
{
    public function create()
    {
        return view('swafi.registro-individual', [
            'tiposActivo'   => $this->catalogOptions('tipos_activo'),
            'proveedores'   => $this->catalogOptions('proveedores'),
            'centrosCosto'  => $this->catalogOptions('centros_costo'),
            'plantas'       => $this->catalogOptions('plantas'),
            'ubicaciones'   => $this->catalogOptions('ubicaciones'),
            'responsables'  => $this->catalogOptions('responsables'),
        ]);
    }

    public function store(StoreRegistroIndividualRequest $request)
    {
        DB::transaction(function () use ($request) {
            $userId = auth()->id();

            /*
            |--------------------------------------------------------------------------
            | Preparación documental
            |--------------------------------------------------------------------------
            | Los archivos se almacenan en el disco local privado de Laravel.
            | En MySQL se guarda la referencia documental: ruta, nombre, MIME, tamaño,
            | hash SHA-256, versión y usuario que realizó la carga.
            */

            $documentos = $this->prepareUploadedDocuments($request);
            $estatusDocumental = $this->resolveDocumentStatus($documentos);

            /*
            |--------------------------------------------------------------------------
            | Alta o actualización del activo
            |--------------------------------------------------------------------------
            */

            $activo = Activo::updateOrCreate(
                ['numero_activo' => $request->numero_activo],
                [
                    'tipo_activo_id'      => $request->tipo_activo_id,
                    'proveedor_id'        => $request->proveedor_id,
                    'centro_costo_id'     => $request->centro_costo_id,
                    'planta_id'           => $request->planta_id,
                    'ubicacion_id'        => $request->ubicacion_id,
                    'responsable_id'      => $request->responsable_id,
                    'descripcion'         => $request->descripcion,
                    'serie'               => $request->serie,
                    'marca'               => $request->marca,
                    'modelo'              => $request->modelo,
                    'fecha_adquisicion'   => $request->fecha_adquisicion,
                    'estatus_operativo'   => $request->estatus_operativo,
                    'estatus_documental'  => $estatusDocumental,
                    'activo'              => true,
                    'creado_por'          => $userId,
                    'actualizado_por'     => $userId,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Alta del expediente
            |--------------------------------------------------------------------------
            */

            $expediente = Expediente::create([
                'numero_activo'    => $activo->numero_activo,
                'folio_factura'    => $request->folio_factura,
                'uuid_cfdi'        => $request->uuid_cfdi,
                'fecha_factura'    => $request->fecha_factura,
                'monto_factura'    => $request->monto_factura,
                'moneda'           => $request->moneda,
                'estatus'          => $estatusDocumental,
                'observaciones'    => $request->observaciones,
                'creado_por'       => $userId,
                'actualizado_por'  => $userId,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Alta de documentos asociados
            |--------------------------------------------------------------------------
            */

            foreach ($documentos as $doc) {
                DocumentoExpediente::create([
                    'expediente_id'   => $expediente->id,
                    'tipo_documento'  => $doc['tipo_documento'],
                    'nombre_archivo'  => $doc['nombre_archivo'],
                    'ruta_archivo'    => $doc['ruta_archivo'],
                    'mime_type'       => $doc['mime_type'],
                    'tamano_bytes'    => $doc['tamano_bytes'],
                    'hash_sha256'     => $doc['hash_sha256'],
                    'version'         => 1,
                    'vigente'         => true,
                    'cargado_por'     => $userId,
                ]);
            }
        });

        return redirect()
            ->route('registro-individual')
            ->with('success', 'El expediente se guardó correctamente con su soporte documental.');
    }

    private function prepareUploadedDocuments(StoreRegistroIndividualRequest $request): array
    {
        if (!$request->hasFile('documentos')) {
            return [];
        }

        $docs = [];

        $numeroActivo = Str::slug($request->numero_activo ?: 'activo-sin-numero');
        $folioFactura = Str::slug($request->folio_factura ?: 'factura-sin-folio');

        $basePath = "swafi/expedientes/{$numeroActivo}/{$folioFactura}";

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
            $safeBaseName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));

            if (!$safeBaseName) {
                $safeBaseName = 'documento';
            }

            $storedName = now()->format('YmdHis')
                . '_'
                . Str::random(8)
                . '_'
                . $safeBaseName
                . '.'
                . $extension;

            $path = $file->storeAs($basePath, $storedName, 'local');

            $absolutePath = Storage::disk('local')->path($path);

            $docs[] = [
                'tipo_documento' => $tipoDocumento,
                'nombre_archivo' => $originalName,
                'ruta_archivo'   => $path,
                'mime_type'      => $file->getMimeType(),
                'tamano_bytes'   => $file->getSize(),
                'hash_sha256'    => hash_file('sha256', $absolutePath),
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

        if (in_array('PDF', $tipos, true) && in_array('XML', $tipos, true)) {
            return 'completo';
        }

        return 'incompleto';
    }

    private function catalogOptions(string $table)
    {
        if (!Schema::hasTable($table)) {
            return collect();
        }

        $rows = DB::table($table)->get();

        return $rows->map(function ($row) {
            $data = (array) $row;

            $label = $data['nombre']
                ?? $data['descripcion']
                ?? $data['clave']
                ?? $data['codigo']
                ?? $data['rfc']
                ?? ('Registro ' . ($data['id'] ?? ''));

            if (isset($data['rfc']) && isset($data['nombre'])) {
                $label = $data['nombre'] . ' (' . $data['rfc'] . ')';
            }

            return (object) [
                'id' => $data['id'] ?? null,
                'label' => $label,
            ];
        });
    }
}
