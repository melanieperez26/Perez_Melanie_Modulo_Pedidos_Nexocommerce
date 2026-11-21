<?php

declare(strict_types=1);

namespace App\Infraestructura\Servicios;

use App\Aplicacion\Notificaciones\Contratos\PublicadorConfirmacionPedidoInterface;
use App\Aplicacion\Notificaciones\DTOs\MensajeConfirmacionPedidoDTO;
use App\Aplicacion\Notificaciones\Excepciones\PublicacionConfirmacionFallida;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

final class RabbitMqConfirmacionPedidoPublisher implements PublicadorConfirmacionPedidoInterface
{
    public function publicar(MensajeConfirmacionPedidoDTO $mensaje): void
    {
        $connection = null;
        $channel = null;

        try {
            $host = (string) config('services.rabbitmq.host');
            $user = (string) config('services.rabbitmq.user');
            $password = (string) config('services.rabbitmq.password');
            $vhost = (string) config('services.rabbitmq.vhost', '/');
            $queue = (string) config('services.rabbitmq.queue', 'pedidos.notificaciones');
            $connectionTimeout = (float) config('services.rabbitmq.connection_timeout', 2);
            $confirmTimeout = (float) config('services.rabbitmq.confirm_timeout', 5);

            if ($host === '' || $user === '' || $password === '' || $vhost === '' || $queue === '') {
                throw new PublicacionConfirmacionFallida($mensaje->message_id, $mensaje->correlation_id);
            }

            $connection = new AMQPStreamConnection(
                host: $host,
                port: (int) config('services.rabbitmq.port', 5672),
                user: $user,
                password: $password,
                vhost: $vhost,
                connection_timeout: $connectionTimeout,
                read_write_timeout: max($confirmTimeout + 1, $connectionTimeout),
            );
            $channel = $connection->channel();

            $acknowledged = false;
            $rejected = false;
            $returned = false;

            $channel->set_ack_handler(static function (AMQPMessage $confirmedMessage) use (&$acknowledged): void {
                $acknowledged = true;
            });
            $channel->set_nack_handler(static function (AMQPMessage $rejectedMessage) use (&$rejected): void {
                $rejected = true;
            });
            $channel->set_return_listener(static function () use (&$returned): void {
                $returned = true;
            });
            $channel->confirm_select();

            $body = json_encode($mensaje->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $amqpMessage = new AMQPMessage($body, [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $mensaje->message_id,
                'correlation_id' => $mensaje->correlation_id,
                'timestamp' => time(),
                'type' => 'pedido.confirmacion.solicitada',
            ]);

            $channel->basic_publish($amqpMessage, '', $queue, true);
            $channel->wait_for_pending_acks_returns($confirmTimeout);

            if (! $acknowledged || $rejected || $returned) {
                throw new PublicacionConfirmacionFallida($mensaje->message_id, $mensaje->correlation_id);
            }
        } catch (PublicacionConfirmacionFallida $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new PublicacionConfirmacionFallida($mensaje->message_id, $mensaje->correlation_id, $exception);
        } finally {
            try {
                if ($channel?->is_open()) {
                    $channel->close();
                }
            } catch (Throwable) {
            }

            try {
                if ($connection?->isConnected()) {
                    $connection->close();
                }
            } catch (Throwable) {
            }
        }
    }
}
