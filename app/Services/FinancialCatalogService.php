<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialCatalogService
{
    public function currencies(): Collection
    {
        return DB::table('monedas')
            ->where('estatus', 'activo')
            ->orderByRaw("CASE WHEN clave = 'MXN' THEN 0 ELSE 1 END")
            ->orderBy('nombre')
            ->get();
    }

    public function accountingStatuses(): Collection
    {
        return DB::table('estatus_contables')
            ->where('estatus', 'activo')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();
    }

    public function currencyRequiresExchangeRate(string $currency): bool
    {
        $value = DB::table('monedas')
            ->where('clave', mb_strtoupper(trim($currency), 'UTF-8'))
            ->where('estatus', 'activo')
            ->value('requiere_tipo_cambio');

        return (bool) $value;
    }
}
