<?php

declare(strict_types=1);

namespace App\Aplicacion\Notificaciones\Contratos;

interface LiquidacionEntregaConfirmacionInterface
{
    public function confirmar(int $deliveryTag): void;

    public function rechazarSinReencolar(int $deliveryTag): void;

    public function reencolar(int $deliveryTag): void;
}
