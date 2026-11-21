<?php

declare(strict_types=1);

namespace App\Aplicacion\Notificaciones\Contratos;

use App\Aplicacion\Notificaciones\DTOs\ResultadoProcesamientoConfirmacionPedidoDTO;
use App\Aplicacion\Notificaciones\Excepciones\MensajeConfirmacionPedidoInvalido;

interface ProcesadorConfirmacionPedidoInterface
{
    /** @throws MensajeConfirmacionPedidoInvalido */
    public function procesar(string $cuerpo): ResultadoProcesamientoConfirmacionPedidoDTO;
}
