<?php

declare(strict_types=1);

namespace App\Infraestructura\Persistencia\Eloquent\Repositorios;

use App\Aplicacion\Notificaciones\Contratos\RegistroProcesamientoConfirmacionPedidoInterface;
use App\Aplicacion\Notificaciones\DTOs\MensajeConfirmacionPedidoDTO;
use App\Infraestructura\Persistencia\Eloquent\Modelos\ProcesamientoConfirmacionPedidoModelo;
use Illuminate\Support\Facades\DB;

final class RegistroProcesamientoConfirmacionPedidoEloquent implements RegistroProcesamientoConfirmacionPedidoInterface
{
    public function registrarSiNuevo(MensajeConfirmacionPedidoDTO $mensaje): bool
    {
        return DB::transaction(fn (): bool => ProcesamientoConfirmacionPedidoModelo::query()->insertOrIgnore([
            'message_id' => $mensaje->message_id,
            'correlation_id' => $mensaje->correlation_id,
            'pedido_id' => $mensaje->pedido_id,
            'tipo' => 'pedido.confirmacion.solicitada',
            'version' => 1,
            'ocurrido_en' => $mensaje->ocurrido_en,
            'resultado' => 'PROCESADO',
            'procesado_en' => now(),
        ]) === 1);
    }
}
