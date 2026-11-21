<?php

declare(strict_types=1);

namespace App\Aplicacion\Notificaciones\Servicios;

use App\Aplicacion\Notificaciones\Contratos\LiquidacionEntregaConfirmacionInterface;
use App\Aplicacion\Notificaciones\Contratos\ProcesadorConfirmacionPedidoInterface;
use App\Aplicacion\Notificaciones\DTOs\EntregaConfirmacionPedidoDTO;
use App\Aplicacion\Notificaciones\Excepciones\MensajeConfirmacionPedidoInvalido;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final readonly class ManejadorEntregaConfirmacionPedido
{
    public function __construct(private ProcesadorConfirmacionPedidoInterface $procesador) {}

    public function manejar(
        EntregaConfirmacionPedidoDTO $entrega,
        LiquidacionEntregaConfirmacionInterface $liquidacion,
    ): void {
        $correlationId = $this->obtenerCorrelationId($entrega->cuerpo);

        try {
            $resultado = $this->procesador->procesar($entrega->cuerpo);
        } catch (MensajeConfirmacionPedidoInvalido $exception) {
            $liquidacion->rechazarSinReencolar($entrega->delivery_tag);
            Log::warning('Mensaje de confirmacion rechazado como permanente.', [
                'message_id' => $exception->messageId,
                'correlation_id' => $correlationId,
                'error' => $exception::class,
            ]);

            return;
        } catch (Throwable $exception) {
            if ($entrega->redelivered) {
                $liquidacion->rechazarSinReencolar($entrega->delivery_tag);
                $accion = 'enviado_a_dlq_tras_reintento';
            } else {
                $liquidacion->reencolar($entrega->delivery_tag);
                $accion = 'reencolado_una_vez';
            }

            Log::warning('Fallo transitorio al procesar confirmacion de pedido.', [
                'correlation_id' => $correlationId,
                'error' => $exception::class,
                'accion' => $accion,
            ]);

            return;
        }

        $liquidacion->confirmar($entrega->delivery_tag);

        Log::info('Confirmacion de pedido procesada por consumidor asincrono.', [
            'message_id' => $resultado->message_id,
            'correlation_id' => $resultado->correlation_id,
            'duplicado' => $resultado->duplicado,
        ]);
    }

    private function obtenerCorrelationId(string $cuerpo): ?string
    {
        try {
            $datos = json_decode($cuerpo, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $valor = is_array($datos) ? ($datos['correlation_id'] ?? null) : null;

        return is_string($valor) && (bool) Str::isUuid($valor) ? $valor : null;
    }
}
