<?php

namespace App\Http\Controllers;

use App\Services\AssetStatusCatalogService;
use App\Services\FinancialCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpedienteGestionController extends Controller
{
    public function __construct(
        private readonly AssetStatusCatalogService $statusCatalogs,
        private readonly FinancialCatalogService $financialCatalogs
    ) {
    }

    public function edit(int $expediente): View
    {
        $detalle = $this->findEditableExpediente($expediente);

        return view('swafi.expediente-editar', [
            'expediente' => $detalle,
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

    public function update(Request $request, int $expediente): RedirectResponse
    {
        $detalle = $this->findEditableExpediente($expediente);

        $validated = $request->validate([
            'folio_factura' => ['required', 'string', 'max:80'],
            'uuid_cfdi' => ['nullable', 'string', 'max:50'],
            'fecha_factura' => ['required', 'date'],
            'monto_factura' => ['required', 'numeric', 'min:0'],
            'moneda' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
                Rule::exists('monedas', 'clave')->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'tipo_activo_id' => ['required', 'integer', 'exists:tipos_activo,id'],
            'centro_costo_id' => ['required', 'integer', 'exists:centros_costo,id'],
            'planta_id' => ['required', 'integer', 'exists:plantas,id'],
            'ubicacion_id' => ['nullable', 'integer', 'exists:ubicaciones,id'],
            'responsable_id' => ['nullable', 'integer', 'exists:responsables,id'],
            'descripcion' => ['required', 'string', 'max:255'],
            'serie' => ['nullable', 'string', 'max:120'],
            'marca' => ['nullable', 'string', 'max:100'],
            'modelo' => ['nullable', 'string', 'max:100'],
            'fecha_adquisicion' => ['nullable', 'date'],
            'estatus_operativo' => [
                'required',
                'string',
                'max:20',
                Rule::exists('estatus_operativos', 'clave')
                    ->where(fn ($query) => $query->where('estatus', 'activo')),
            ],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ], [
            'folio_factura.required' => 'El folio de factura es obligatorio.',
            'fecha_factura.required' => 'La fecha de factura es obligatoria.',
            'monto_factura.required' => 'El monto de factura es obligatorio.',
            'moneda.size' => 'La moneda debe capturarse con tres letras.',
            'moneda.regex' => 'La moneda solo puede contener letras mayúsculas.',
            'moneda.exists' => 'La moneda seleccionada no existe o se encuentra inactiva.',
            'proveedor_id.required' => 'Debes seleccionar un proveedor.',
            'tipo_activo_id.required' => 'Debes seleccionar un tipo de activo.',
            'centro_costo_id.required' => 'Debes seleccionar un centro de costo.',
            'planta_id.required' => 'Debes seleccionar una planta.',
            'descripcion.required' => 'La descripción del activo es obligatoria.',
            'estatus_operativo.required' => 'El estatus operativo es obligatorio.',
            'estatus_operativo.exists' => 'El estatus operativo seleccionado no existe o está inactivo.',
        ]);

        $numeroActivo = $detalle->numero_activo;
        $folioFactura = trim($validated['folio_factura']);
        $uuidCfdi = trim((string) ($validated['uuid_cfdi'] ?? '')) ?: null;

        $folioConflict = DB::table('expedientes')
            ->where('numero_activo', $numeroActivo)
            ->where('folio_factura', $folioFactura)
            ->where('id', '<>', $detalle->expediente_id)
            ->exists();

        if ($folioConflict) {
            return back()
                ->withErrors(['folio_factura' => 'Ya existe otro expediente para este activo con el mismo folio de factura.'])
                ->withInput();
        }

        DB::transaction(function () use ($detalle, $validated, $folioFactura, $uuidCfdi, $request) {
            $antes = [
                'activo' => DB::table('activos')->where('numero_activo', $detalle->numero_activo)->first(),
                'expediente' => DB::table('expedientes')->where('id', $detalle->expediente_id)->whereNull('deleted_at')->first(),
            ];

            DB::table('activos')
                ->where('numero_activo', $detalle->numero_activo)
                ->update([
                    'tipo_activo_id' => $validated['tipo_activo_id'],
                    'proveedor_id' => $validated['proveedor_id'],
                    'centro_costo_id' => $validated['centro_costo_id'],
                    'planta_id' => $validated['planta_id'],
                    'ubicacion_id' => $validated['ubicacion_id'] ?? null,
                    'responsable_id' => $validated['responsable_id'] ?? null,
                    'descripcion' => $validated['descripcion'],
                    'serie' => $validated['serie'] ?? null,
                    'marca' => $validated['marca'] ?? null,
                    'modelo' => $validated['modelo'] ?? null,
                    'fecha_adquisicion' => $validated['fecha_adquisicion'] ?? null,
                    'estatus_operativo' => $validated['estatus_operativo'],
                    'actualizado_por' => auth()->id(),
                    'updated_at' => now(),
                ]);

            DB::table('expedientes')
                ->where('id', $detalle->expediente_id)
                ->whereNull('deleted_at')
                ->update([
                    'folio_factura' => $folioFactura,
                    'uuid_cfdi' => $uuidCfdi,
                    'fecha_factura' => $validated['fecha_factura'],
                    'monto_factura' => $validated['monto_factura'],
                    'moneda' => $validated['moneda'],
                    'observaciones' => $validated['observaciones'] ?? null,
                    'actualizado_por' => auth()->id(),
                    'updated_at' => now(),
                ]);

            $despues = [
                'activo' => DB::table('activos')->where('numero_activo', $detalle->numero_activo)->first(),
                'expediente' => DB::table('expedientes')->where('id', $detalle->expediente_id)->whereNull('deleted_at')->first(),
            ];

            $this->registrarBitacora(
                numeroActivo: $detalle->numero_activo,
                accion: 'EXPEDIENTE_ACTUALIZADO',
                tablaAfectada: 'expedientes',
                registroClave: (string) $detalle->expediente_id,
                antes: $antes,
                despues: $despues,
                ip: $request->ip()
            );
        });

        return redirect()
            ->route('expediente', $detalle->expediente_id)
            ->with('success', 'El expediente fue actualizado correctamente.');
    }

    public function destroy(Request $request, int $expediente): RedirectResponse
    {
        $detalle = $this->findEditableExpediente($expediente);
        $numeroActivo = $detalle->numero_activo;
        $validated = $request->validate([
            'motivo_baja' => ['nullable', 'string', 'max:500'],
        ]);
        $motivoBaja = trim((string) ($validated['motivo_baja'] ?? ''))
            ?: 'Baja lógica solicitada desde la búsqueda avanzada.';

        DB::transaction(function () use ($detalle, $numeroActivo, $request, $motivoBaja) {
            $antes = [
                'expediente' => DB::table('expedientes')->where('id', $detalle->expediente_id)->whereNull('deleted_at')->first(),
                'documentos' => DB::table('documentos_expediente')
                    ->where('expediente_id', $detalle->expediente_id)
                    ->get()
                    ->toArray(),
            ];

            DB::table('expedientes')
                ->where('id', $detalle->expediente_id)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => now(),
                    'deleted_by' => auth()->id(),
                    'delete_reason' => $motivoBaja,
                    'actualizado_por' => auth()->id(),
                    'updated_at' => now(),
                ]);

            $despues = DB::table('expedientes')
                ->where('id', $detalle->expediente_id)
                ->first();

            $this->registrarBitacora(
                numeroActivo: $numeroActivo,
                accion: 'EXPEDIENTE_BAJA_LOGICA',
                tablaAfectada: 'expedientes',
                registroClave: (string) $detalle->expediente_id,
                antes: $antes,
                despues: [
                    'expediente' => $despues,
                    'mensaje' => 'Baja lógica aplicada. El expediente, documentos y trazabilidad permanecen almacenados.',
                    'motivo_baja' => $motivoBaja,
                ],
                ip: $request->ip()
            );

            $this->actualizarEstatusDocumentalActivo($numeroActivo);
        });

        return redirect()
            ->route('busqueda', ['numero_activo' => $numeroActivo])
            ->with('success', 'El expediente fue dado de baja lógicamente. El activo, sus documentos y la trazabilidad se conservan.');
    }

    private function findEditableExpediente(int $expediente): object
    {
        $detalle = DB::table('expedientes as e')
            ->join('activos as a', 'a.numero_activo', '=', 'e.numero_activo')
            ->where('e.id', $expediente)
            ->whereNull('e.deleted_at')
            ->select([
                'e.id as expediente_id',
                'e.folio_factura',
                'e.uuid_cfdi',
                'e.fecha_factura',
                'e.monto_factura',
                'e.moneda',
                'e.estatus as expediente_estatus',
                'e.observaciones',
                'a.numero_activo',
                'a.tipo_activo_id',
                'a.proveedor_id',
                'a.centro_costo_id',
                'a.planta_id',
                'a.ubicacion_id',
                'a.responsable_id',
                'a.descripcion as activo_descripcion',
                'a.serie',
                'a.marca',
                'a.modelo',
                'a.fecha_adquisicion',
                'a.estatus_operativo',
                'a.estatus_documental',
            ])
            ->first();

        abort_if(!$detalle, 404, 'El expediente solicitado no existe.');

        return $detalle;
    }

    private function actualizarEstatusDocumentalActivo(string $numeroActivo): void
    {
        $expedientes = DB::table('expedientes')
            ->where('numero_activo', $numeroActivo)
            ->whereNull('deleted_at')
            ->pluck('id');

        if ($expedientes->isEmpty()) {
            DB::table('activos')
                ->where('numero_activo', $numeroActivo)
                ->update([
                    'estatus_documental' => 'incompleto',
                    'actualizado_por' => auth()->id(),
                    'updated_at' => now(),
                ]);

            return;
        }

        $todosCompletos = true;

        foreach ($expedientes as $expedienteId) {
            $tipos = DB::table('documentos_expediente')
                ->where('expediente_id', $expedienteId)
                ->where('vigente', true)
                ->pluck('tipo_documento')
                ->map(fn ($tipo) => strtoupper((string) $tipo))
                ->unique()
                ->values()
                ->all();

            if (!in_array('PDF', $tipos, true) || !in_array('XML', $tipos, true)) {
                $todosCompletos = false;
                break;
            }
        }

        DB::table('activos')
            ->where('numero_activo', $numeroActivo)
            ->update([
                'estatus_documental' => $todosCompletos ? 'completo' : 'incompleto',
                'actualizado_por' => auth()->id(),
                'updated_at' => now(),
            ]);
    }

    private function catalogOptions(string $table)
    {
        if (!Schema::hasTable($table)) {
            return collect();
        }

        $rows = DB::table($table)
            ->when(Schema::hasColumn($table, 'estatus'), function ($query) {
                $query->where('estatus', 'activo');
            })
            ->get();

        return $rows->map(function ($row) {
            $data = (array) $row;

            $label = $data['nombre']
                ?? $data['descripcion']
                ?? $data['clave']
                ?? $data['codigo_interno']
                ?? $data['codigo']
                ?? $data['rfc']
                ?? ('Registro ' . ($data['id'] ?? ''));

            if (isset($data['rfc']) && isset($data['nombre'])) {
                $label = $data['nombre'] . ' (' . $data['rfc'] . ')';
            }

            if (isset($data['clave']) && isset($data['descripcion'])) {
                $label = $data['clave'] . ' - ' . $data['descripcion'];
            }

            return (object) [
                'id' => $data['id'] ?? null,
                'label' => $label,
            ];
        });
    }

    private function registrarBitacora(
        ?string $numeroActivo,
        string $accion,
        ?string $tablaAfectada,
        ?string $registroClave,
        mixed $antes,
        mixed $despues,
        ?string $ip
    ): void {
        try {
            if (!Schema::hasTable('bitacora_auditoria')) {
                return;
            }

            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $numeroActivo,
                'user_id' => session('swafi_user_id') ?: auth()->id(),
                'modulo' => 'M03 Consultas',
                'accion' => $accion,
                'tabla_afectada' => $tablaAfectada,
                'registro_clave' => $registroClave,
                'antes' => $antes ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
                'despues' => $despues ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
                'ip' => $ip,
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            app(\App\Services\SafeExceptionReporter::class)->warning(
                $exception,
                'asset_record_audit_write',
                [
                    'user_id' => auth()->id(),
                    'route_name' => request()->route()?->getName(),
                ]
            );
        }
    }
}
