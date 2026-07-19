<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ValorActivoFichaService
{
    public function findCurrent(string $numeroActivo): ?object
    {
        $latestExpedientes = DB::table('expedientes')
            ->whereNull('deleted_at')
            ->select('numero_activo', DB::raw('MAX(id) as expediente_id'))
            ->groupBy('numero_activo');

        return DB::table('valores_activo as v')
            ->whereNull('v.deleted_at')
            ->join('activos as a', 'a.numero_activo', '=', 'v.numero_activo')
            ->leftJoinSub(
                $latestExpedientes,
                'le',
                fn ($join) => $join->on('le.numero_activo', '=', 'a.numero_activo')
            )
            ->leftJoin('expedientes as e', 'e.id', '=', 'le.expediente_id')
            ->leftJoin('proveedores as p', 'p.id', '=', 'a.proveedor_id')
            ->leftJoin('centros_costo as cc', 'cc.id', '=', 'a.centro_costo_id')
            ->leftJoin('plantas as pl', 'pl.id', '=', 'a.planta_id')
            ->leftJoin('tipos_activo as ta', 'ta.id', '=', 'a.tipo_activo_id')
            ->leftJoin('cfdi_validaciones as cv', 'cv.id', '=', 'v.cfdi_validacion_id')
            ->leftJoin('users as u', 'u.id', '=', 'v.registrado_por')
            ->where('v.numero_activo', $numeroActivo)
            ->first([
                'v.id as valor_id',
                'v.numero_activo',
                'v.valor_fiscal',
                'v.valor_financiero',
                'v.moneda',
                'v.tipo_cambio',
                'v.fecha_tipo_cambio',
                'v.origen_tipo_cambio',
                'v.depreciacion_acumulada',
                'v.valor_en_libros',
                'v.vida_util_meses',
                'v.estatus_contable',
                'v.motivo_cambio',
                'v.conciliacion_cfdi',
                'v.conciliacion_detalle',
                'v.fecha_corte',
                'v.created_at',
                'v.updated_at',
                'a.descripcion as activo_descripcion',
                'a.estatus_operativo',
                'a.estatus_documental',
                'e.id as expediente_id',
                'e.folio_factura',
                'e.uuid_cfdi',
                'e.fecha_factura',
                'e.monto_factura',
                'e.moneda as moneda_factura',
                'p.nombre as proveedor_nombre',
                'p.rfc as proveedor_rfc',
                'cc.clave as centro_costo_clave',
                'cc.descripcion as centro_costo_descripcion',
                'pl.nombre as planta_nombre',
                'ta.descripcion as tipo_activo',
                'cv.estatus_validacion as cfdi_estatus',
                'cv.total as cfdi_total',
                'cv.moneda as cfdi_moneda',
                'cv.uuid_cfdi as cfdi_uuid',
                'u.name as registrado_por_nombre',
                'u.email as registrado_por_email',
            ]);
    }

    /**
     * @return array{title:string,headers:array<int,string>,rows:array<int,array<int,mixed>>}
     */
    public function xlsxPayload(object $record): array
    {
        return [
            'title' => $this->sheetTitle($record),
            'headers' => [
                'Número de activo',
                'Descripción',
                'Tipo de activo',
                'Planta',
                'Centro de costo',
                'Proveedor',
                'RFC proveedor',
                'Folio factura',
                'UUID CFDI',
                'Fecha factura',
                'Monto factura',
                'Moneda factura',
                'Valor fiscal',
                'Depreciación acumulada',
                'Valor en libros',
                'Valor financiero',
                'Moneda',
                'Tipo de cambio',
                'Fecha tipo de cambio',
                'Origen tipo de cambio',
                'Vida útil en meses',
                'Fecha de corte',
                'Estatus contable',
                'Conciliación CFDI',
                'Estatus validación CFDI',
                'Total CFDI',
                'Moneda CFDI',
                'Estatus documental',
                'Estatus operativo',
                'Motivo del último cambio',
                'Registrado por',
                'Última actualización',
            ],
            'rows' => [[
                $record->numero_activo,
                $record->activo_descripcion,
                $record->tipo_activo,
                $record->planta_nombre,
                $this->costCenter($record),
                $record->proveedor_nombre,
                $record->proveedor_rfc,
                $record->folio_factura,
                $record->uuid_cfdi,
                $record->fecha_factura,
                $this->decimalOrNull($record->monto_factura),
                $record->moneda_factura,
                $this->decimalOrNull($record->valor_fiscal),
                $this->decimalOrNull($record->depreciacion_acumulada),
                $this->decimalOrNull($record->valor_en_libros),
                $this->decimalOrNull($record->valor_financiero),
                $record->moneda,
                $this->decimalOrNull($record->tipo_cambio, 6),
                $record->fecha_tipo_cambio,
                $record->origen_tipo_cambio,
                $record->vida_util_meses !== null ? (int) $record->vida_util_meses : null,
                $record->fecha_corte,
                $this->statusLabel($record->estatus_contable),
                $this->statusLabel($record->conciliacion_cfdi),
                $this->statusLabel($record->cfdi_estatus),
                $this->decimalOrNull($record->cfdi_total),
                $record->cfdi_moneda,
                $this->statusLabel($record->estatus_documental),
                $this->statusLabel($record->estatus_operativo),
                $record->motivo_cambio,
                $this->registeredBy($record),
                $record->updated_at,
            ]],
        ];
    }

    /**
     * @return array{title:string,headers:array<int,string>,rows:array<int,array<int,string>>}
     */
    public function pdfPayload(object $record): array
    {
        $moneyCurrency = trim((string) ($record->moneda ?: 'MXN'));
        $invoiceCurrency = trim((string) ($record->moneda_factura ?: 'MXN'));

        return [
            'title' => 'Ficha fiscal y financiera · ' . $record->numero_activo,
            'headers' => ['Sección', 'Campo', 'Valor'],
            'rows' => [
                ['Identificación', 'Número de activo', $this->display($record->numero_activo)],
                ['Identificación', 'Descripción', $this->display($record->activo_descripcion)],
                ['Identificación', 'Tipo de activo', $this->display($record->tipo_activo)],
                ['Identificación', 'Planta', $this->display($record->planta_nombre)],
                ['Identificación', 'Centro de costo', $this->display($this->costCenter($record))],
                ['Identificación', 'Estatus documental', $this->display($this->statusLabel($record->estatus_documental))],
                ['Identificación', 'Estatus operativo', $this->display($this->statusLabel($record->estatus_operativo))],

                ['Factura', 'Proveedor', $this->display($record->proveedor_nombre)],
                ['Factura', 'RFC del proveedor', $this->display($record->proveedor_rfc)],
                ['Factura', 'Folio', $this->display($record->folio_factura)],
                ['Factura', 'UUID CFDI', $this->display($record->uuid_cfdi)],
                ['Factura', 'Fecha', $this->displayDate($record->fecha_factura)],
                ['Factura', 'Monto', $this->money($record->monto_factura, $invoiceCurrency)],

                ['Valores', 'Valor fiscal', $this->money($record->valor_fiscal, $moneyCurrency)],
                ['Valores', 'Depreciación acumulada', $this->money($record->depreciacion_acumulada, $moneyCurrency)],
                ['Valores', 'Valor en libros', $this->money($record->valor_en_libros, $moneyCurrency)],
                ['Valores', 'Valor financiero', $this->money($record->valor_financiero, $moneyCurrency)],
                ['Valores', 'Moneda', $this->display($record->moneda)],
                ['Valores', 'Tipo de cambio', $this->decimalDisplay($record->tipo_cambio, 6)],
                ['Valores', 'Fecha tipo de cambio', $this->displayDate($record->fecha_tipo_cambio)],
                ['Valores', 'Origen tipo de cambio', $this->display($record->origen_tipo_cambio)],
                ['Valores', 'Vida útil', $record->vida_util_meses !== null
                    ? ((int) $record->vida_util_meses) . ' meses'
                    : 'Sin definir'],
                ['Valores', 'Fecha de corte', $this->displayDate($record->fecha_corte)],
                ['Valores', 'Estatus contable', $this->display($this->statusLabel($record->estatus_contable))],
                ['Valores', 'Motivo del último cambio', $this->display($record->motivo_cambio)],

                ['Conciliación CFDI', 'Resultado', $this->display($this->statusLabel($record->conciliacion_cfdi))],
                ['Conciliación CFDI', 'Validación técnica', $this->display($this->statusLabel($record->cfdi_estatus))],
                ['Conciliación CFDI', 'UUID extraído', $this->display($record->cfdi_uuid)],
                ['Conciliación CFDI', 'Total extraído', $this->money($record->cfdi_total, (string) ($record->cfdi_moneda ?: $moneyCurrency))],
                ['Conciliación CFDI', 'Moneda extraída', $this->display($record->cfdi_moneda)],

                ['Trazabilidad', 'Registrado por', $this->display($this->registeredBy($record))],
                ['Trazabilidad', 'Fecha de creación', $this->displayDateTime($record->created_at)],
                ['Trazabilidad', 'Última actualización', $this->displayDateTime($record->updated_at)],
            ],
        ];
    }

    public function fileBase(object $record): string
    {
        $safeAsset = preg_replace('/[^A-Z0-9._-]+/i', '_', (string) $record->numero_activo);
        $safeAsset = trim((string) $safeAsset, '._-');

        return 'ficha_fiscal_financiera_' . ($safeAsset !== '' ? $safeAsset : 'activo')
            . '_' . now()->format('Ymd_His');
    }

    private function sheetTitle(object $record): string
    {
        $title = 'Ficha ' . (string) $record->numero_activo;

        return mb_substr($title, 0, 31, 'UTF-8');
    }

    private function costCenter(object $record): ?string
    {
        $key = trim((string) ($record->centro_costo_clave ?? ''));
        $description = trim((string) ($record->centro_costo_descripcion ?? ''));

        if ($key === '') {
            return $description !== '' ? $description : null;
        }

        return $description !== '' ? $key . ' — ' . $description : $key;
    }

    private function registeredBy(object $record): ?string
    {
        $name = trim((string) ($record->registrado_por_nombre ?? ''));
        $email = trim((string) ($record->registrado_por_email ?? ''));

        if ($name !== '' && $email !== '') {
            return $name . ' (' . $email . ')';
        }

        return $name !== '' ? $name : ($email !== '' ? $email : null);
    }

    private function decimalOrNull(mixed $value, int $scale = 2): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, $scale);
    }

    private function statusLabel(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_convert_case(
            str_replace('_', ' ', mb_strtolower($value, 'UTF-8')),
            MB_CASE_TITLE,
            'UTF-8'
        );
    }

    private function display(mixed $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : 'Sin información';
    }

    private function displayDate(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 'Sin información';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function displayDateTime(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 'Sin información';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y H:i:s');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function money(mixed $value, string $currency): string
    {
        if ($value === null || $value === '') {
            return 'Sin información';
        }

        $currency = trim($currency) !== '' ? mb_strtoupper(trim($currency), 'UTF-8') : 'MXN';

        return $currency . ' ' . number_format((float) $value, 2, '.', ',');
    }

    private function decimalDisplay(mixed $value, int $scale = 2): string
    {
        if ($value === null || $value === '') {
            return 'Sin información';
        }

        return number_format((float) $value, $scale, '.', ',');
    }
}
