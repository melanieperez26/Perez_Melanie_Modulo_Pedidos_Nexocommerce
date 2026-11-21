<?php

declare(strict_types=1);

namespace Tests\Unitarias\Notificaciones;

use App\Aplicacion\Notificaciones\CasosUso\ProcesarMensajeConfirmacionPedido;
use App\Aplicacion\Notificaciones\Contratos\RegistroProcesamientoConfirmacionPedidoInterface;
use App\Aplicacion\Notificaciones\DTOs\MensajeConfirmacionPedidoDTO;
use App\Aplicacion\Notificaciones\Excepciones\MensajeConfirmacionPedidoInvalido;
use App\Aplicacion\Notificaciones\Servicios\ValidadorMensajeConfirmacionPedido;
use App\Dominio\Pedidos\Entidades\Pedido;
use App\Dominio\Pedidos\Repositorios\PedidoRepositorioInterface;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

final class ProcesarMensajeConfirmacionPedidoTest extends TestCase
{
    private const string MESSAGE_ID = 'e4095617-5fc8-4f0d-a2f1-098cdd367ea1';

    private const string CORRELATION_ID = '42a29a33-f750-44a0-81e3-50c393afad53';

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_procesa_pedido_existente_y_conserva_la_correlacion(): void
    {
        $pedidos = Mockery::mock(PedidoRepositorioInterface::class);
        $pedidos->shouldReceive('buscarPorId')->once()->with(3)->andReturn($this->pedido(3));
        $registros = new RegistroEnMemoriaConfirmacion;
        $casoUso = new ProcesarMensajeConfirmacionPedido($pedidos, $registros, new ValidadorMensajeConfirmacionPedido);

        $resultado = $casoUso->procesar($this->mensaje());

        self::assertSame(self::MESSAGE_ID, $resultado->message_id);
        self::assertSame(self::CORRELATION_ID, $resultado->correlation_id);
        self::assertFalse($resultado->duplicado);
        self::assertCount(1, $registros->mensajes);
        self::assertSame(self::CORRELATION_ID, $registros->mensajes[self::MESSAGE_ID]->correlation_id);
    }

    public function test_message_id_repetido_no_crea_un_segundo_registro(): void
    {
        $pedidos = Mockery::mock(PedidoRepositorioInterface::class);
        $pedidos->shouldReceive('buscarPorId')->twice()->with(3)->andReturn($this->pedido(3));
        $registros = new RegistroEnMemoriaConfirmacion;
        $casoUso = new ProcesarMensajeConfirmacionPedido($pedidos, $registros, new ValidadorMensajeConfirmacionPedido);

        $primero = $casoUso->procesar($this->mensaje());
        $segundo = $casoUso->procesar($this->mensaje());

        self::assertFalse($primero->duplicado);
        self::assertTrue($segundo->duplicado);
        self::assertCount(1, $registros->mensajes);
    }

    public function test_contrato_invalido_se_clasifica_como_error_permanente(): void
    {
        $pedidos = Mockery::mock(PedidoRepositorioInterface::class);
        $pedidos->shouldNotReceive('buscarPorId');
        $registros = Mockery::mock(RegistroProcesamientoConfirmacionPedidoInterface::class);
        $registros->shouldNotReceive('registrarSiNuevo');
        $casoUso = new ProcesarMensajeConfirmacionPedido($pedidos, $registros, new ValidadorMensajeConfirmacionPedido);

        try {
            $casoUso->procesar('{"message_id":"invalido"}');
            self::fail('Se esperaba un error permanente de validacion.');
        } catch (MensajeConfirmacionPedidoInvalido) {
            self::assertTrue(true);
        }
    }

    public function test_pedido_inexistente_se_clasifica_como_error_permanente(): void
    {
        $pedidos = Mockery::mock(PedidoRepositorioInterface::class);
        $pedidos->shouldReceive('buscarPorId')->once()->with(3)->andReturnNull();
        $registros = Mockery::mock(RegistroProcesamientoConfirmacionPedidoInterface::class);
        $registros->shouldNotReceive('registrarSiNuevo');
        $casoUso = new ProcesarMensajeConfirmacionPedido($pedidos, $registros, new ValidadorMensajeConfirmacionPedido);

        $this->expectException(MensajeConfirmacionPedidoInvalido::class);
        $casoUso->procesar($this->mensaje());
    }

    private function mensaje(): string
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

    private function pedido(int $id): Pedido
    {
        return new Pedido($id, 99, '2026-12-01', 'PENDIENTE', '1.00', '1.00', new DateTimeImmutable('2026-11-20T21:00:00+00:00'));
    }
}

final class RegistroEnMemoriaConfirmacion implements RegistroProcesamientoConfirmacionPedidoInterface
{
    /** @var array<string, MensajeConfirmacionPedidoDTO> */
    public array $mensajes = [];

    public function registrarSiNuevo(MensajeConfirmacionPedidoDTO $mensaje): bool
    {
        if (isset($this->mensajes[$mensaje->message_id])) {
            return false;
        }

        $this->mensajes[$mensaje->message_id] = $mensaje;

        return true;
    }
}
