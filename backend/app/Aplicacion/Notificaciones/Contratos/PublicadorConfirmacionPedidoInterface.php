<?php

declare(strict_types=1);

namespace App\Aplicacion\Notificaciones\Contratos;

use App\Aplicacion\Notificaciones\DTOs\MensajeConfirmacionPedidoDTO;

interface PublicadorConfirmacionPedidoInterface
{
    public function publicar(MensajeConfirmacionPedidoDTO $mensaje): void;
}
