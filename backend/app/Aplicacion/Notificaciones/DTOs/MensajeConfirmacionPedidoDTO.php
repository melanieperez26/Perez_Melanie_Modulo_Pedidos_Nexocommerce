<?php

declare(strict_types=1);

namespace App\Aplicacion\Notificaciones\DTOs;

final readonly class MensajeConfirmacionPedidoDTO
{
    public function __construct(
        public string $message_id,
        public string $correlation_id,
        public string $ocurrido_en,
        public int $pedido_id,
    ) {}

    /**
     * @return array{
     *     message_id: string,
     *     correlation_id: string,
     *     tipo: string,
     *     version: int,
     *     ocurrido_en: string,
     *     payload: array{pedido_id: int}
     * }
     */
    public function toArray(): array
    {
        return [
            'message_id' => $this->message_id,
            'correlation_id' => $this->correlation_id,
            'tipo' => 'pedido.confirmacion.solicitada',
            'version' => 1,
            'ocurrido_en' => $this->ocurrido_en,
            'payload' => [
                'pedido_id' => $this->pedido_id,
            ],
        ];
    }
}
