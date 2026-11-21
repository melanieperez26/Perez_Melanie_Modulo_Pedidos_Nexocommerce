<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notificaciones;

use App\Aplicacion\Notificaciones\CasosUso\ProcesarConfirmacionPedidoSincrona;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notificaciones\SolicitarConfirmacionPedidoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

final class ConfirmacionPedidoSincronaController extends Controller
{
    public function __construct(private readonly ProcesarConfirmacionPedidoSincrona $procesar) {}

    public function store(SolicitarConfirmacionPedidoRequest $request, int $id): JsonResponse
    {
        $mensaje = $this->procesar->execute(
            $id,
            (int) $request->user()->getAuthIdentifier(),
            $request->validated('correlation_id'),
        );

        Log::info('Confirmacion de laboratorio procesada en modalidad sincrona.', [
            'message_id' => $mensaje->message_id,
            'correlation_id' => $mensaje->correlation_id,
            'pedido_id' => $mensaje->pedido_id,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'message_id' => $mensaje->message_id,
                'correlation_id' => $mensaje->correlation_id,
                'modalidad' => 'sincrona',
                'resultado' => 'pedido_validado_y_consultado',
            ],
        ])->header('X-Correlation-ID', $mensaje->correlation_id);
    }
}
