<?php

declare(strict_types=1);

namespace App\Aplicacion\Notificaciones\CasosUso;

use App\Aplicacion\Notificaciones\Contratos\ProcesadorConfirmacionPedidoInterface;
use App\Aplicacion\Notificaciones\Contratos\RegistroProcesamientoConfirmacionPedidoInterface;
use App\Aplicacion\Notificaciones\DTOs\ResultadoProcesamientoConfirmacionPedidoDTO;
use App\Aplicacion\Notificaciones\Excepciones\MensajeConfirmacionPedidoInvalido;
use App\Aplicacion\Notificaciones\Servicios\ValidadorMensajeConfirmacionPedido;
use App\Dominio\Pedidos\Repositorios\PedidoRepositorioInterface;

final readonly class ProcesarMensajeConfirmacionPedido implements ProcesadorConfirmacionPedidoInterface
{
    public function __construct(
        private PedidoRepositorioInterface $pedidos,
        private RegistroProcesamientoConfirmacionPedidoInterface $registros,
        private ValidadorMensajeConfirmacionPedido $validador,
    ) {}

    public function procesar(string $cuerpo): ResultadoProcesamientoConfirmacionPedidoDTO
    {
        $datos = $this->validador->validar($cuerpo);
        $pedido = $this->pedidos->buscarPorId($datos->pedido_id);

        if ($pedido === null) {
            throw new MensajeConfirmacionPedidoInvalido($datos->message_id);
        }

        $duplicado = ! $this->registros->registrarSiNuevo($datos);

        return new ResultadoProcesamientoConfirmacionPedidoDTO(
            $datos->message_id,
            $datos->correlation_id,
            $duplicado,
        );
    }
}
