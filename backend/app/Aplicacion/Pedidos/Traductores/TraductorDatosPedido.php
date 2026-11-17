<?php

declare(strict_types=1);

namespace App\Aplicacion\Pedidos\Traductores;

use DateTimeImmutable;
use InvalidArgumentException;

final class TraductorDatosPedido
{
    public function desdeWeb(array $entrada): array
    {
        $this->exigirCampos($entrada, ['fechaEntrega', 'totalEsperado']);

        $fecha = $this->normalizarFechaWeb($entrada['fechaEntrega']);
        $total = $this->normalizarTotalWeb($entrada['totalEsperado']);

        return [
            'fecha_entrega' => $fecha,
            'total_esperado' => $total,
        ];
    }

    public function desdeMovil(array $entrada): array
    {
        $this->exigirCampos($entrada, ['delivery_date', 'expected_total']);

        $fecha = $entrada['delivery_date'];
        $total = $entrada['expected_total'];

        if (!is_string($fecha) ||
            !$this->fechaValida($fecha, 'Y-m-d')) {
            throw new InvalidArgumentException(
                'La fecha móvil debe tener el formato YYYY-MM-DD.'
            );
        }

        if (!is_int($total) && !is_float($total)) {
            throw new InvalidArgumentException(
                'El total móvil debe ser numérico.'
            );
        }

        if (!is_finite((float) $total) ||
            $total < 0 ||
            $total > 99999999.99) {
            throw new InvalidArgumentException(
                'El total móvil está fuera del rango permitido.'
            );
        }

        $normalizado = number_format((float) $total, 2, '.', '');

        if (abs((float) $normalizado - (float) $total) > 0.00000001) {
            throw new InvalidArgumentException(
                'El total móvil no puede tener más de dos decimales.'
            );
        }

        return [
            'fecha_entrega' => $fecha,
            'total_esperado' => $normalizado,
        ];
    }

    private function exigirCampos(array $entrada, array $campos): void
    {
        foreach ($campos as $campo) {
            if (!array_key_exists($campo, $entrada) ||
                $entrada[$campo] === null) {
                throw new InvalidArgumentException(
                    "El campo {$campo} es obligatorio."
                );
            }
        }
    }

    private function normalizarFechaWeb(mixed $fecha): string
    {
        if (!is_string($fecha) ||
            !$this->fechaValida($fecha, 'd/m/Y')) {
            throw new InvalidArgumentException(
                'La fecha web debe tener el formato DD/MM/YYYY.'
            );
        }

        return DateTimeImmutable::createFromFormat(
            '!d/m/Y',
            $fecha
        )->format('Y-m-d');
    }

    private function normalizarTotalWeb(mixed $total): string
    {
        if (!is_string($total) ||
            !preg_match('/^(?:0|[1-9]\d{0,7}),\d{2}$/D', $total)) {
            throw new InvalidArgumentException(
                'El total web debe utilizar coma y dos decimales.'
            );
        }

        return str_replace(',', '.', $total);
    }

    private function fechaValida(string $fecha, string $formato): bool
    {
        $objeto = DateTimeImmutable::createFromFormat(
            '!' . $formato,
            $fecha
        );

        return $objeto !== false &&
            $objeto->format($formato) === $fecha;
    }
}
