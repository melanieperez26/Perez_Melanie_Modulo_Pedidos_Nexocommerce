<?php

declare(strict_types=1);

namespace App\Http\Controllers\Orquestacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ResumenPedidoController extends Controller
{
    public function show(Request $request, int $id): JsonResponse
    {
        $cid = $request->header('X-Correlation-ID');

        if (! is_string($cid) || trim($cid) === '') {
            $cid = (string) Str::uuid();
        }

        try {
            // Servicio HTTP de usuarios
            $usuarios = Http::timeout(3)
                ->withHeaders(['X-Correlation-ID' => $cid])
                ->get(config('orquestacion.usuarios_url') . '/usuarios/7');

            // Servicio HTTP de estados
            $estados = Http::timeout(3)
                ->withHeaders(['X-Correlation-ID' => $cid])
                ->get(config('orquestacion.estados_url') . '/estados/' . $id);

            // Comprobar las respuestas HTTP
            if (! $usuarios->successful() || ! $estados->successful()) {
                throw new \RuntimeException('Dependencia HTTP no disponible');
            }

            $usuario = $usuarios->json();
            $estado = $estados->json();

            // Comprobar la estructura de las respuestas
            if (
                ! is_array($usuario) ||
                ! isset($usuario['id'], $usuario['nombre']) ||
                ! is_array($estado) ||
                ! isset($estado['solicitud_id'], $estado['estado']) ||
                (int) $estado['solicitud_id'] !== $id
            ) {
                throw new \RuntimeException('Respuesta de dependencia inválida');
            }

            return response()->json([
                'pedido_id' => $id,
                'usuario' => $usuario,
                'estado' => $estado['estado'],
                'correlation_id' => $cid,
            ])->header('X-Correlation-ID', $cid);

        } catch (Throwable $ex) {
            Log::warning('Falla de orquestación de pedido', [
                'correlation_id' => $cid,
                'pedido_id' => $id,
                'tipo_error' => get_class($ex),
            ]);

            return response()->json([
                'error' => 'Dependencia no disponible',
                'correlation_id' => $cid,
            ], 502)->header('X-Correlation-ID', $cid);
        }
    }


    public function showParcial(Request $request, int $id): JsonResponse
    {
        $cid = $request->header('X-Correlation-ID');

        if (! is_string($cid) || trim($cid) === '') {
            $cid = (string) Str::uuid();
        }

        $resultado = [
            'pedido_id' => $id,
        ];

        // Consultar servicio de usuarios
        try {
            $usuarios = Http::timeout(3)
                ->withHeaders(['X-Correlation-ID' => $cid])
                ->get(config('orquestacion.usuarios_url') . '/usuarios/7');

            if ($usuarios->successful()) {
                $datos = $usuarios->json();

                if (is_array($datos) && isset($datos['id'], $datos['nombre'])) {
                    $resultado['usuario'] = $datos;
                }
            }
        } catch (Throwable $ex) {
            Log::warning('Servicio usuarios no disponible', [
                'correlation_id' => $cid,
                'tipo_error' => get_class($ex),
            ]);
        }

        // Consultar servicio de estados
        try {
            $estados = Http::timeout(3)
                ->withHeaders(['X-Correlation-ID' => $cid])
                ->get(config('orquestacion.estados_url') . '/estados/' . $id);

            if ($estados->successful()) {
                $datos = $estados->json();

                if (
                    is_array($datos) &&
                    isset($datos['solicitud_id'], $datos['estado']) &&
                    (int) $datos['solicitud_id'] === $id
                ) {
                    $resultado['estado'] = $datos['estado'];
                }
            }
        } catch (Throwable $ex) {
            Log::warning('Servicio estados no disponible', [
                'correlation_id' => $cid,
                'tipo_error' => get_class($ex),
            ]);
        }

        $resultado['correlation_id'] = $cid;

        if (! isset($resultado['usuario']) && ! isset($resultado['estado'])) {
            return response()->json([
                'error' => 'Dependencias no disponibles',
                'correlation_id' => $cid,
            ], 502)->header('X-Correlation-ID', $cid);
        }

        return response()->json($resultado, 200)
            ->header('X-Correlation-ID', $cid);
    }

}
