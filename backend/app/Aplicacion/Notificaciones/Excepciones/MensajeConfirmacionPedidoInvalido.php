<?php

declare(strict_types=1);

namespace App\Aplicacion\Notificaciones\Excepciones;

use RuntimeException;

final class MensajeConfirmacionPedidoInvalido extends RuntimeException
{
    public function __construct(public readonly ?string $messageId = null, ?\Throwable $previous = null)
    {
        parent::__construct('El mensaje de confirmacion de pedido no cumple el contrato.', 0, $previous);
    }
}
