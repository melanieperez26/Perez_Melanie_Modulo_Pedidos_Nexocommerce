<?php

declare(strict_types=1);

namespace Tests\Unitarias\Notificaciones;

use App\Infraestructura\Servicios\RabbitMqLiquidacionEntregaConfirmacion;
use Mockery;
use PhpAmqpLib\Channel\AMQPChannel;
use PHPUnit\Framework\TestCase;

final class RabbitMqLiquidacionEntregaConfirmacionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_confirma_rechaza_y_reencola_con_las_opciones_amqp_esperadas(): void
    {
        $llamadas = [];
        $canal = Mockery::mock(AMQPChannel::class);
        $canal->shouldReceive('basic_ack')->once()->with(7, false)->andReturnUsing(function (...$argumentos) use (&$llamadas): void {
            $llamadas[] = ['ack', ...$argumentos];
        });
        $canal->shouldReceive('basic_reject')->once()->with(8, false)->andReturnUsing(function (...$argumentos) use (&$llamadas): void {
            $llamadas[] = ['reject', ...$argumentos];
        });
        $canal->shouldReceive('basic_nack')->once()->with(9, false, true)->andReturnUsing(function (...$argumentos) use (&$llamadas): void {
            $llamadas[] = ['nack', ...$argumentos];
        });
        $liquidacion = new RabbitMqLiquidacionEntregaConfirmacion($canal);

        $liquidacion->confirmar(7);
        $liquidacion->rechazarSinReencolar(8);
        $liquidacion->reencolar(9);

        self::assertSame([
            ['ack', 7, false],
            ['reject', 8, false],
            ['nack', 9, false, true],
        ], $llamadas);
    }
}
