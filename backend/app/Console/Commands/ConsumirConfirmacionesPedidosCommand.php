<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infraestructura\Servicios\RabbitMqConfirmacionPedidoConsumer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ConsumirConfirmacionesPedidosCommand extends Command
{
    protected $signature = 'laboratorio:consumir-confirmaciones-pedidos {--once : Procesa como maximo una entrega y termina} {--reconnect-delay=3 : Segundos entre intentos de reconexion}';

    protected $description = 'Consume confirmaciones de pedidos de la cola de laboratorio RabbitMQ';

    private bool $detener = false;

    public function handle(RabbitMqConfirmacionPedidoConsumer $consumer): int
    {
        if (function_exists('pcntl_signal') && defined('SIGINT') && defined('SIGTERM')) {
            $this->trap([SIGINT, SIGTERM], function (): void {
                $this->detener = true;
                $this->components->info('Cierre solicitado; se completara la entrega en curso.');
            });
        }

        $unaVez = (bool) $this->option('once');
        $espera = max(1, (int) $this->option('reconnect-delay'));

        do {
            try {
                $consumer->consumir($unaVez, fn (): bool => $this->detener);
            } catch (Throwable $exception) {
                Log::error('Consumidor RabbitMQ desconectado o no disponible.', ['error' => $exception::class]);
                $this->components->error('RabbitMQ no disponible o conexion interrumpida. Consulte el log por correlation_id.');

                if ($unaVez) {
                    return self::FAILURE;
                }

                sleep($espera);
            }
        } while (! $unaVez && ! $this->detener);

        return self::SUCCESS;
    }
}
