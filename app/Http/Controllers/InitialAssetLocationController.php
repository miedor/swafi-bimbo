<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmInitialAssetLocationRequest;
use App\Services\InitialAssetLocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class InitialAssetLocationController extends Controller
{
    public function store(
        ConfirmInitialAssetLocationRequest $request,
        int $expediente,
        InitialAssetLocationService $service
    ): RedirectResponse {
        $userId = (int) (Auth::id() ?: $request->session()->get('swafi_user_id'));

        $service->confirm(
            expedienteId: $expediente,
            data: $request->validated(),
            userId: $userId,
            ipAddress: $request->ip()
        );

        return redirect()
            ->route('expediente', [
                'expediente' => $expediente,
                'tab' => 'ubicacion',
            ])
            ->with('success', 'La ubicación inicial del activo fue confirmada y quedó registrada con trazabilidad.');
    }
}
