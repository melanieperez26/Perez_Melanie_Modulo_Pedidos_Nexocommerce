<?php

declare(strict_types=1);

namespace Tests\Unitarias\Notificaciones;

use App\Aplicacion\Notificaciones\Contratos\LiquidacionEntregaConfirmacionInterface;
use App\Aplicacion\Notificaciones\Contratos\ProcesadorConfirmacionPedidoInterface;
use App\Aplicacion\Notificaciones\DTOs\EntregaConfirmacionPedidoDTO;
use App\Aplicacion\Notificaciones\DTOs\ResultadoProcesamientoConfirmacionPedidoDTO;
use App\Aplicacion\Notificaciones\Excepciones\MensajeConfirmacionPedidoInvalido;
use App\Aplicacion\Notificaciones\Servicios\ManejadorEntregaConfirmacionPedido;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

final class ManejadorEntregaConfirmacionPedidoTest extends TestCase
{
    private const string MESSAGE_ID = 'e4095617-5fc8-4f0d-a2f1-098cdd367ea1';

    private const string CORRELATION_ID = '42a29a33-f750-44a0-81e3-50c393afad53';

    public function test_confirma_solo_despues_de_que_el_procesamiento_termina_y_registra_correlacion(): void
    {
        $orden = [];
        $procesador = Mockery::mock(ProcesadorConfirmacionPedidoInterface::class);
        $procesador->shouldReceive('procesar')->once()->andReturnUsing(function () use (&$orden): ResultadoProcesamientoConfirmacionPedidoDTO {
            $orden[] = 'transaccion_completada';

            return new ResultadoProcesamientoConfirmacionPedidoDTO(self::MESSAGE_ID, self::CORRELATION_ID, false);
        });
        $liquidacion = new LiquidacionEnMemoriaConfirmacion($orden);
        Log::shouldReceive('info')->once()->with('Confirmacion de pedido procesada por consumidor asincrono.', Mockery::on(
            fn (array $contexto): bool => $contexto['correlation_id'] === self::CORRELATION_ID,
        ));
        $manejador = new ManejadorEntregaConfirmacionPedido($procesador);

        $manejador->manejar(new EntregaConfirmacionPedidoDTO($this->cuerpo(), 7, false), $liquidacion);

        self::assertSame(['transaccion_completada', 'ack'], $orden);
    }

    public function test_error_permanente_rechaza_sin_reencolar_para_la_dlq(): void
    {
        $procesador = Mockery::mock(ProcesadorConfirmacionPedidoInterface::class);
        $procesador->shouldReceive('procesar')->once()->andThrow(new MensajeConfirmacionPedidoInvalido(self::MESSAGE_ID));
        $liquidacion = Mockery::mock(LiquidacionEntregaConfirmacionInterface::class);
        $liquidacion->shouldReceive('rechazarSinReencolar')->once()->with(7);
        $liquidacion->shouldNotReceive('confirmar');
        $liquidacion->shouldNotReceive('reencolar');
        Log::shouldReceive('warning')->once();
        $manejador = new ManejadorEntregaConfirmacionPedido($procesador);

        $manejador->manejar(new EntregaConfirmacionPedidoDTO($this->cuerpo(), 7, false), $liquidacion);
    }

    public function test_error_transitorio_reencola_una_vez_y_redelivery_se_envia_a_dlq(): void
    {
        $procesador = Mockery::mock(ProcesadorConfirmacionPedidoInterface::class);
        $procesador->shouldReceive('procesar')->twice()->andThrow(new \RuntimeException('Fallo simulado'));
        $primeraLiquidacion = Mockery::mock(LiquidacionEntregaConfirmacionInterface::class);
        $primeraLiquidacion->shouldReceive('reencolar')->once()->with(7);
        Log::shouldReceive('warning')->twice();
        $manejador = new ManejadorEntregaConfirmacionPedido($procesador);

        $manejador->manejar(new EntregaConfirmacionPedidoDTO($this->cuerpo(), 7, false), $primeraLiquidacion);

        $segundaLiquidacion = Mockery::mock(LiquidacionEntregaConfirmacionInterface::class);
        $segundaLiquidacion->shouldReceive('rechazarSinReencolar')->once()->with(8);
        $manejador->manejar(new EntregaConfirmacionPedidoDTO($this->cuerpo(), 8, true), $segundaLiquidacion);
    }

    private function cuerpo(): string
    {
        return json_encode([
            'message_id' => self::MESSAGE_ID,
            'correlation_id' => self::CORRELATION_ID,
            'tipo' => 'pedido.confirmacion.solicitada',
            'version' => 1,
            'ocurrido_en' => '2026-11-20T21:00:00+00:00',
            'payload' => ['pedido_id' => 3],
        ], JSON_THROW_ON_ERROR);
    }
}

final class LiquidacionEnMemoriaConfirmacion implements LiquidacionEntregaConfirmacionInterface
{
    /** @param list<string> $orden */
    public function __construct(private array &$orden) {}

    public function confirmar(int $deliveryTag): void
    {
        $this->orden[] = 'ack';
    }

    public function rechazarSinReencolar(int $deliveryTag): void {}

    public function reencolar(int $deliveryTag): void {}
}
