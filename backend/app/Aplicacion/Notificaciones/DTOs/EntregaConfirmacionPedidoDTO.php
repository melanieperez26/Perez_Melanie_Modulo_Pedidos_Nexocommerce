<?php

declare(strict_types=1);

namespace App\Aplicacion\Notificaciones\DTOs;

final readonly class EntregaConfirmacionPedidoDTO
{
    public function __construct(
        public string $cuerpo,
        public int $delivery_tag,
        public bool $redelivered,
    ) {}
}
