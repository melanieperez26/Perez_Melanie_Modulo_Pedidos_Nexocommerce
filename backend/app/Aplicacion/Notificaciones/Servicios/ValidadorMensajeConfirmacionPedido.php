<?php

declare(strict_types=1);

namespace App\Aplicacion\Notificaciones\Servicios;

use App\Aplicacion\Notificaciones\DTOs\MensajeConfirmacionPedidoDTO;
use App\Aplicacion\Notificaciones\Excepciones\MensajeConfirmacionPedidoInvalido;
use DateTimeImmutable;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

final class ValidadorMensajeConfirmacionPedido
{
    public function validar(string $cuerpo): MensajeConfirmacionPedidoDTO
    {
        try {
            $datos = json_decode($cuerpo, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new MensajeConfirmacionPedidoInvalido(previous: $exception);
        }

        if (! is_array($datos)
            || array_is_list($datos)
            || ! $this->tieneClavesExactas($datos, ['message_id', 'correlation_id', 'tipo', 'version', 'ocurrido_en', 'payload'])
            || ! is_string($datos['message_id'])
            || ! (bool) Str::isUuid($datos['message_id'])
            || ! is_string($datos['correlation_id'])
            || ! (bool) Str::isUuid($datos['correlation_id'])
            || $datos['tipo'] !== 'pedido.confirmacion.solicitada'
            || $datos['version'] !== 1
            || ! is_string($datos['ocurrido_en'])
            || ! $this->esFechaIso8601($datos['ocurrido_en'])
            || ! is_array($datos['payload'])
            || ! $this->tieneClavesExactas($datos['payload'], ['pedido_id'])
            || ! is_int($datos['payload']['pedido_id'])
            || $datos['payload']['pedido_id'] < 1) {
            $messageId = is_array($datos) && is_string($datos['message_id'] ?? null) && Str::isUuid($datos['message_id'])
                ? $datos['message_id']
                : null;

            throw new MensajeConfirmacionPedidoInvalido($messageId);
        }

        return new MensajeConfirmacionPedidoDTO(
            $datos['message_id'],
            $datos['correlation_id'],
            $datos['ocurrido_en'],
            $datos['payload']['pedido_id'],
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<string>  $claves
     */
    private function tieneClavesExactas(array $datos, array $claves): bool
    {
        $actuales = array_keys($datos);
        sort($actuales);
        sort($claves);

        return $actuales === $claves;
    }

    private function esFechaIso8601(string $valor): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $valor)) {
            return false;
        }

        $partesFecha = date_parse($valor);
        if (($partesFecha['error_count'] ?? 1) > 0 || ($partesFecha['warning_count'] ?? 1) > 0) {
            return false;
        }

        try {
            new DateTimeImmutable($valor);
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
