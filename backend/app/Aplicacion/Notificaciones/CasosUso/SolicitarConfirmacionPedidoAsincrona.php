<?php

declare(strict_types=1);

namespace App\Aplicacion\Notificaciones\CasosUso;

use App\Aplicacion\Notificaciones\Contratos\PublicadorConfirmacionPedidoInterface;
use App\Aplicacion\Notificaciones\DTOs\MensajeConfirmacionPedidoDTO;
use App\Dominio\Compartido\Excepciones\DominioException;
use App\Dominio\Pedidos\Repositorios\PedidoRepositorioInterface;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Str;

final readonly class SolicitarConfirmacionPedidoAsincrona
{
    public function __construct(
        private PedidoRepositorioInterface $pedidos,
        private PublicadorConfirmacionPedidoInterface $publicador,
    ) {}

    public function execute(int $pedidoId, int $usuarioId, ?string $correlationId): MensajeConfirmacionPedidoDTO
    {
        $pedido = $this->pedidos->buscarPorId($pedidoId)
            ?? throw new DominioException('Pedido no encontrado.', 'PED_NO_ENCONTRADO', 404);

        if ($pedido->usuario_id !== $usuarioId) {
            throw new DominioException('Pedido de otro usuario.', 'PED_AJENO', 403);
        }

        $mensaje = new MensajeConfirmacionPedidoDTO(
            message_id: (string) Str::uuid(),
            correlation_id: $correlationId ?? (string) Str::uuid(),
            ocurrido_en: (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.uP'),
            pedido_id: $pedido->id,
        );

        $this->publicador->publicar($mensaje);

        return $mensaje;
    }
}
