<?php

declare(strict_types=1);

namespace App\Aplicacion\Notificaciones\DTOs;

final readonly class ResultadoProcesamientoConfirmacionPedidoDTO
{
    public function __construct(
        public string $message_id,
        public string $correlation_id,
        public bool $duplicado,
    ) {}
}
