<?php

declare(strict_types=1);

namespace App\Aplicacion\Notificaciones\Contratos;

use App\Aplicacion\Notificaciones\DTOs\MensajeConfirmacionPedidoDTO;

interface RegistroProcesamientoConfirmacionPedidoInterface
{
    /** Guarda el resultado en una transacción y devuelve false si message_id ya fue registrado. */
    public function registrarSiNuevo(MensajeConfirmacionPedidoDTO $mensaje): bool;
}
