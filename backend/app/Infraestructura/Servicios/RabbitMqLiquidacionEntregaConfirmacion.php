<?php

declare(strict_types=1);

namespace App\Infraestructura\Servicios;

use App\Aplicacion\Notificaciones\Contratos\LiquidacionEntregaConfirmacionInterface;
use PhpAmqpLib\Channel\AMQPChannel;

final readonly class RabbitMqLiquidacionEntregaConfirmacion implements LiquidacionEntregaConfirmacionInterface
{
    public function __construct(private AMQPChannel $canal) {}

    public function confirmar(int $deliveryTag): void
    {
        $this->canal->basic_ack($deliveryTag, false);
    }

    public function rechazarSinReencolar(int $deliveryTag): void
    {
        $this->canal->basic_reject($deliveryTag, false);
    }

    public function reencolar(int $deliveryTag): void
    {
        $this->canal->basic_nack($deliveryTag, false, true);
    }
}
