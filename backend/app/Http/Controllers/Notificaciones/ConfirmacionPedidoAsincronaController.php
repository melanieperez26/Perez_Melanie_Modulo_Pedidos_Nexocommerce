<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notificaciones;

use App\Aplicacion\Notificaciones\CasosUso\SolicitarConfirmacionPedidoAsincrona;
use App\Aplicacion\Notificaciones\Excepciones\PublicacionConfirmacionFallida;
use App\Dominio\Compartido\Excepciones\DominioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notificaciones\SolicitarConfirmacionPedidoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ConfirmacionPedidoAsincronaController extends Controller
{
    public function __construct(private readonly SolicitarConfirmacionPedidoAsincrona $solicitar) {}

    public function store(SolicitarConfirmacionPedidoRequest $request, int $id): JsonResponse
    {
        $usuarioId = (int) $request->user()->getAuthIdentifier();
        $correlationId = $request->validated('correlation_id');

        try {
            $mensaje = $this->solicitar->execute($id, $usuarioId, $correlationId);
        } catch (PublicacionConfirmacionFallida $exception) {
            Log::warning('No se pudo publicar la confirmacion asincrona del pedido.', [
                'pedido_id' => $id,
                'message_id' => $exception->message_id,
                'correlation_id' => $exception->correlation_id ?? $correlationId,
                'tipo_error' => get_class($exception->getPrevious() ?? $exception),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'El servicio de mensajeria no esta disponible.',
                'codigo_error' => 'RABBITMQ_UNAVAILABLE',
                'correlation_id' => $exception->correlation_id ?? $correlationId,
            ], 503);
        } catch (Throwable $exception) {
            if ($exception instanceof DominioException) {
                throw $exception;
            }

            Log::error('Error inesperado al solicitar confirmacion asincrona.', [
                'pedido_id' => $id,
                'correlation_id' => $correlationId,
                'tipo_error' => get_class($exception),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo aceptar la solicitud de confirmacion.',
                'codigo_error' => 'CONFIRMATION_REQUEST_FAILED',
            ], 500);
        }

        Log::info('Confirmacion de pedido aceptada por RabbitMQ.', [
            'pedido_id' => $id,
            'message_id' => $mensaje->message_id,
            'correlation_id' => $mensaje->correlation_id,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'message_id' => $mensaje->message_id,
                'correlation_id' => $mensaje->correlation_id,
                'estado' => 'pendiente',
            ],
        ], 202)->header('X-Correlation-ID', $mensaje->correlation_id);
    }
}
