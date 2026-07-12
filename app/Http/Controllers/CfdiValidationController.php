<?php

namespace App\Http\Controllers;

use App\Services\CfdiValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class CfdiValidationController extends Controller
{
    public function revalidate(int $expediente, CfdiValidationService $service): RedirectResponse
    {
        $expedienteData = DB::table('expedientes')->where('id', $expediente)->first();
        abort_if(!$expedienteData, 404, 'El expediente solicitado no existe.');

        $result = $service->validateExpedienteXmls($expediente, auth()->id());

        return redirect()
            ->route('expediente', $expediente)
            ->with(
                $result['invalidados'] > 0 ? 'warning' : 'success',
                sprintf(
                    'Validación CFDI finalizada: %d XML procesado(s), %d válido(s), %d observado(s) y %d inválido(s).',
                    $result['procesados'],
                    $result['validos'],
                    $result['observados'],
                    $result['invalidados']
                )
            );
    }
}
