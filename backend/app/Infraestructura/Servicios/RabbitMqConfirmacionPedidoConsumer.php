<?php

declare(strict_types=1);

namespace App\Infraestructura\Servicios;

use App\Aplicacion\Notificaciones\DTOs\EntregaConfirmacionPedidoDTO;
use App\Aplicacion\Notificaciones\Servicios\ManejadorEntregaConfirmacionPedido;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use Throwable;

final readonly class RabbitMqConfirmacionPedidoConsumer
{
    /** @param array{host:string,port:int,user:string,password:string,vhost:string,queue:string,connection_timeout:float,confirm_timeout:float} $config */
    public function __construct(
        private ManejadorEntregaConfirmacionPedido $manejador,
        private array $config,
    ) {}

    /** @param callable():bool $debeDetener */
    public function consumir(bool $unaVez, callable $debeDetener): void
    {
        if ($this->config['host'] === '' || $this->config['user'] === '' || $this->config['password'] === ''
            || $this->config['vhost'] === '' || $this->config['queue'] === '') {
            throw new \RuntimeException('La configuracion AMQP del consumidor esta incompleta.');
        }

        $conexion = new AMQPStreamConnection(
            host: $this->config['host'],
            port: $this->config['port'],
            user: $this->config['user'],
            password: $this->config['password'],
            vhost: $this->config['vhost'],
            connection_timeout: $this->config['connection_timeout'],
            read_write_timeout: max(65, $this->config['connection_timeout']),
            heartbeat: 30,
            channel_rpc_timeout: 5,
        );
        $canal = null;

        try {
            $canal = $conexion->channel();
            $canal->basic_qos(0, 1, false);
            $procesado = false;
            $callback = function ($mensaje) use ($canal, &$procesado): void {
                $liquidacion = new RabbitMqLiquidacionEntregaConfirmacion($canal);
                $this->manejador->manejar(
                    new EntregaConfirmacionPedidoDTO(
                        (string) $mensaje->getBody(),
                        (int) $mensaje->getDeliveryTag(),
                        (bool) $mensaje->isRedelivered(),
                    ),
                    $liquidacion,
                );
                $procesado = true;
            };

            $canal->basic_consume($this->config['queue'], '', false, false, false, false, $callback);

            while ($canal->is_consuming() && ! $debeDetener() && (! $unaVez || ! $procesado)) {
                try {
                    $canal->wait(null, false, 1.0);
                } catch (AMQPTimeoutException) {
                    // El timeout permite revisar señales de cierre sin abandonar la conexión.
                }
            }
        } finally {
            $this->cerrarConexion($canal, $conexion);
        }
    }

    private function cerrarConexion(?AMQPChannel $canal, AMQPStreamConnection $conexion): void
    {
        try {
            if ($canal !== null && $canal->is_open()) {
                $canal->close();
            }
        } catch (Throwable $exception) {
            Log::debug('No se pudo cerrar limpiamente el canal RabbitMQ.', ['error' => $exception::class]);
        }

        try {
            if ($conexion->isConnected()) {
                $conexion->close();
            }
        } catch (Throwable $exception) {
            Log::debug('No se pudo cerrar limpiamente la conexion RabbitMQ.', ['error' => $exception::class]);
        }
    }
}
