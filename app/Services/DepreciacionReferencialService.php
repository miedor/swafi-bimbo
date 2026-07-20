<?php

namespace App\Services;

use DateTimeImmutable;
use DomainException;

class DepreciacionReferencialService
{
    public const METODO_LINEA_RECTA = 'linea_recta';

    /**
     * @return array{
     *     metodo:string,
     *     base_financiera:float,
     *     valor_residual:float,
     *     base_depreciable:float,
     *     vida_util_meses:int,
     *     dias_vida_util:int,
     *     dias_transcurridos:int,
     *     porcentaje_depreciado:float,
     *     depreciacion_estimada:float,
     *     valor_en_libros_estimado:float,
     *     fecha_inicio:string,
     *     fecha_fin_vida_util:string,
     *     fecha_corte:string
     * }
     */
    public function calculate(
        string $method,
        float $financialValue,
        float $residualValue,
        int $usefulLifeMonths,
        string $startDate,
        string $cutoffDate
    ): array {
        $method = trim($method);

        if ($method !== self::METODO_LINEA_RECTA) {
            throw new DomainException('El método de depreciación seleccionado todavía no cuenta con una fórmula implementada en SWAFI.');
        }

        if ($financialValue < 0) {
            throw new DomainException('El valor financiero utilizado como base no puede ser negativo.');
        }

        if ($residualValue < 0 || $residualValue > $financialValue) {
            throw new DomainException('El valor residual debe estar entre cero y el valor financiero del activo.');
        }

        if ($usefulLifeMonths < 1 || $usefulLifeMonths > 1200) {
            throw new DomainException('La vida útil debe estar entre 1 y 1200 meses.');
        }

        $start = $this->parseDate($startDate, 'La fecha de inicio de depreciación no es válida.');
        $cutoff = $this->parseDate($cutoffDate, 'La fecha de corte no es válida.');
        $end = $this->addMonthsNoOverflow($start, $usefulLifeMonths);
        $depreciableBase = round(max($financialValue - $residualValue, 0), 2);
        $totalDays = max(1, (int) $start->diff($end)->format('%a'));

        if ($cutoff <= $start || $depreciableBase === 0.0) {
            $elapsedDays = 0;
        } else {
            $effectiveCutoff = $cutoff < $end ? $cutoff : $end;
            $elapsedDays = min($totalDays, (int) $start->diff($effectiveCutoff)->format('%a'));
        }

        $ratio = min(1, max(0, $elapsedDays / $totalDays));
        $estimatedDepreciation = round($depreciableBase * $ratio, 2);
        $estimatedBookValue = round(max($financialValue - $estimatedDepreciation, $residualValue), 2);

        return [
            'metodo' => $method,
            'base_financiera' => round($financialValue, 2),
            'valor_residual' => round($residualValue, 2),
            'base_depreciable' => $depreciableBase,
            'vida_util_meses' => $usefulLifeMonths,
            'dias_vida_util' => $totalDays,
            'dias_transcurridos' => $elapsedDays,
            'porcentaje_depreciado' => round($ratio * 100, 6),
            'depreciacion_estimada' => $estimatedDepreciation,
            'valor_en_libros_estimado' => $estimatedBookValue,
            'fecha_inicio' => $start->format('Y-m-d'),
            'fecha_fin_vida_util' => $end->format('Y-m-d'),
            'fecha_corte' => $cutoff->format('Y-m-d'),
        ];
    }

    private function parseDate(string $value, string $message): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            throw new DomainException($message);
        }

        return $date;
    }

    private function addMonthsNoOverflow(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $day = (int) $date->format('d');
        $firstDayTargetMonth = $date
            ->modify('first day of this month')
            ->modify('+' . $months . ' months');
        $lastDayTargetMonth = (int) $firstDayTargetMonth->format('t');

        return $firstDayTargetMonth->setDate(
            (int) $firstDayTargetMonth->format('Y'),
            (int) $firstDayTargetMonth->format('m'),
            min($day, $lastDayTargetMonth)
        );
    }
}
