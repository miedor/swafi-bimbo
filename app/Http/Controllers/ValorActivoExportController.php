<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportValorActivoFichaRequest;
use App\Services\SafeExceptionReporter;
use App\Services\SimplePdfTableExporter;
use App\Services\SimpleXlsxExporter;
use App\Services\SwafiAuthorizationService;
use App\Services\ValorActivoFichaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ValorActivoExportController extends Controller
{
    public function __construct(
        private readonly SwafiAuthorizationService $authorization,
        private readonly ValorActivoFichaService $fichaService,
        private readonly SimpleXlsxExporter $xlsxExporter,
        private readonly SimplePdfTableExporter $pdfExporter,
        private readonly SafeExceptionReporter $safeExceptions
    ) {
    }

    public function download(ExportValorActivoFichaRequest $request): Response|RedirectResponse
    {
        $data = $request->validated();
        $numeroActivo = (string) $data['numero_activo'];
        $formato = (string) $data['formato'];
        $permission = $formato === 'xlsx'
            ? 'reportes.exportar_excel'
            : 'reportes.exportar_pdf';
        $canViewSensitiveValues = $this->authorization->canCurrentUser('valores.administrar')
            || $this->authorization->canCurrentUser('reportes.valores');

        abort_unless(
            $canViewSensitiveValues,
            403,
            'Tu usuario no tiene permiso para consultar valores fiscales y financieros sensibles.'
        );

        abort_unless(
            $this->authorization->canCurrentUser($permission),
            403,
            $formato === 'xlsx'
                ? 'Tu usuario no tiene permiso para exportar fichas a Excel.'
                : 'Tu usuario no tiene permiso para exportar fichas a PDF.'
        );

        $record = $this->fichaService->findCurrent($numeroActivo);

        abort_if(
            $record === null,
            404,
            'No se encontró una ficha fiscal y financiera vigente para el activo solicitado.'
        );

        try {
            $response = $formato === 'xlsx'
                ? $this->xlsxResponse($record)
                : $this->pdfResponse($record);
        } catch (Throwable $exception) {
            $reference = $this->safeExceptions->warning(
                $exception,
                'asset_value_sheet_export',
                [
                    'asset_number' => $numeroActivo,
                    'format' => $formato,
                    'user_id' => auth()->id(),
                    'route_name' => $request->route()?->getName(),
                ]
            );

            return redirect()
                ->route('valores', [
                    'panel' => 'consulta',
                    'numero_activo' => $numeroActivo,
                ])
                ->withErrors([
                    'exportacion' => "No fue posible generar la ficha solicitada. Referencia: {$reference}.",
                ]);
        }

        $this->registerAudit($request, $record, $formato);

        return $response;
    }

    private function xlsxResponse(object $record): Response
    {
        $payload = $this->fichaService->xlsxPayload($record);
        $contents = $this->xlsxExporter->exportBytes(
            $payload['title'],
            $payload['headers'],
            $payload['rows']
        );
        $filename = $this->fichaService->fileBase($record) . '.xlsx';

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function pdfResponse(object $record): Response
    {
        $payload = $this->fichaService->pdfPayload($record);
        $contents = $this->pdfExporter->export(
            title: $payload['title'],
            headers: $payload['headers'],
            rows: $payload['rows'],
            metadata: [
                'usuario' => session('swafi_nombre', session('swafi_usuario', 'Usuario SWAFI')),
                'fecha' => now()->format('d/m/Y H:i:s'),
                'filtros' => 'Activo: ' . $record->numero_activo . ' | Ficha individual vigente',
            ]
        );
        $filename = $this->fichaService->fileBase($record) . '.pdf';

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function registerAudit(
        ExportValorActivoFichaRequest $request,
        object $record,
        string $formato
    ): void {
        try {
            DB::table('bitacora_auditoria')->insert([
                'numero_activo' => $record->numero_activo,
                'user_id' => auth()->id(),
                'modulo' => 'M02 Control fiscal y financiero',
                'accion' => $formato === 'xlsx'
                    ? 'EXPORTA_FICHA_VALOR_XLSX'
                    : 'EXPORTA_FICHA_VALOR_PDF',
                'tabla_afectada' => 'valores_activo',
                'registro_clave' => (string) $record->valor_id,
                'antes' => null,
                'despues' => json_encode([
                    'formato' => strtoupper($formato),
                    'numero_activo' => $record->numero_activo,
                    'fecha_corte' => $record->fecha_corte,
                    'conciliacion_cfdi' => $record->conciliacion_cfdi,
                ], JSON_UNESCAPED_UNICODE),
                'ip' => $request->ip(),
                'fecha_evento' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->safeExceptions->warning(
                $exception,
                'asset_value_sheet_export_audit',
                [
                    'asset_number' => $record->numero_activo,
                    'value_id' => $record->valor_id,
                    'format' => $formato,
                    'user_id' => auth()->id(),
                    'route_name' => $request->route()?->getName(),
                ]
            );
        }
    }
}
