<?php

declare(strict_types=1);

namespace Tests\Integracion\Notificaciones;

use App\Aplicacion\Notificaciones\Contratos\PublicadorConfirmacionPedidoInterface;
use App\Aplicacion\Notificaciones\DTOs\MensajeConfirmacionPedidoDTO;
use App\Aplicacion\Notificaciones\Excepciones\PublicacionConfirmacionFallida;
use App\Dominio\Pedidos\Entidades\Pedido;
use App\Dominio\Pedidos\Repositorios\PedidoRepositorioInterface;
use App\Infraestructura\Persistencia\Eloquent\Modelos\RolModelo;
use App\Infraestructura\Persistencia\Eloquent\Modelos\UsuarioModelo;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ConfirmacionPedidoAsincronaTest extends TestCase
{
    private const string ENDPOINT = '/api/v1/laboratorio/pedidos/22/confirmaciones';

    public function test_sin_autenticacion_responde_401_sin_acceder_a_dependencias(): void
    {
        $this->postJson(self::ENDPOINT)->assertUnauthorized();
    }

    public function test_admin_no_puede_usar_el_endpoint_exclusivo_para_clientes(): void
    {
        $this->autenticar('ADMIN');

        $this->postJson(self::ENDPOINT)->assertForbidden();
    }

    public function test_rechaza_correlation_id_invalido_sin_publicar(): void
    {
        $this->autenticar('CLIENTE');
        $publicador = Mockery::mock(PublicadorConfirmacionPedidoInterface::class);
        $publicador->shouldNotReceive('publicar');
        $this->app->instance(PublicadorConfirmacionPedidoInterface::class, $publicador);

        $this->postJson(self::ENDPOINT, ['correlation_id' => 'no-es-uuid'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['correlation_id']]);
    }

    public function test_cliente_puede_encolar_confirmacion_de_su_pedido_con_correlacion_proporcionada(): void
    {
        $usuarioId = 15;
        $this->autenticar('CLIENTE', $usuarioId);
        $this->registrarPedido($usuarioId);
        $correlationId = '3e87aeb3-3ccb-44b7-9c5e-38410e010d1c';
        $publicador = Mockery::mock(PublicadorConfirmacionPedidoInterface::class);
        $publicador->shouldReceive('publicar')
            ->once()
            ->with(Mockery::on(function (MensajeConfirmacionPedidoDTO $mensaje) use ($correlationId): bool {
                $this->assertTrue((bool) Str::isUuid($mensaje->message_id));
                $this->assertSame($correlationId, $mensaje->correlation_id);
                $this->assertSame(22, $mensaje->pedido_id);
                $this->assertSame('pedido.confirmacion.solicitada', $mensaje->toArray()['tipo']);
                $this->assertSame(1, $mensaje->toArray()['version']);
                $this->assertSame(['pedido_id' => 22], $mensaje->toArray()['payload']);
                $this->assertNotEmpty($mensaje->ocurrido_en);

                return true;
            }));
        $this->app->instance(PublicadorConfirmacionPedidoInterface::class, $publicador);

        $this->postJson(self::ENDPOINT, ['correlation_id' => $correlationId])
            ->assertAccepted()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.correlation_id', $correlationId)
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertHeader('X-Correlation-ID', $correlationId);
    }

    public function test_cliente_no_puede_encolar_confirmacion_de_pedido_ajeno(): void
    {
        $this->autenticar('CLIENTE', 15);
        $this->registrarPedido(99);
        $publicador = Mockery::mock(PublicadorConfirmacionPedidoInterface::class);
        $publicador->shouldNotReceive('publicar');
        $this->app->instance(PublicadorConfirmacionPedidoInterface::class, $publicador);

        $this->postJson(self::ENDPOINT)->assertForbidden()->assertJsonPath('codigo_error', 'PED_AJENO');
    }

    public function test_cliente_que_omite_correlation_id_recibe_uuids_nuevos_en_cada_solicitud(): void
    {
        $this->autenticar('CLIENTE', 15);
        $pedido = new Pedido(
            id: 22,
            usuario_id: 15,
            fecha_entrega: '2026-12-01',
            estado: 'PENDIENTE',
            subtotal: '25.00',
            total: '25.00',
            creado_en: new DateTimeImmutable('2026-11-20T12:00:00+00:00'),
        );
        $pedidos = Mockery::mock(PedidoRepositorioInterface::class);
        $pedidos->shouldReceive('buscarPorId')->twice()->with(22)->andReturn($pedido);
        $this->app->instance(PedidoRepositorioInterface::class, $pedidos);
        $ids = [];
        $publicador = Mockery::mock(PublicadorConfirmacionPedidoInterface::class);
        $publicador->shouldReceive('publicar')->twice()->andReturnUsing(function (MensajeConfirmacionPedidoDTO $mensaje) use (&$ids): void {
            $ids[] = [$mensaje->message_id, $mensaje->correlation_id];
        });
        $this->app->instance(PublicadorConfirmacionPedidoInterface::class, $publicador);

        $primeraRespuesta = $this->postJson(self::ENDPOINT)->assertAccepted();
        $segundaRespuesta = $this->postJson(self::ENDPOINT)->assertAccepted();

        $this->assertNotSame($primeraRespuesta->json('data.message_id'), $segundaRespuesta->json('data.message_id'));
        $this->assertTrue((bool) Str::isUuid($primeraRespuesta->json('data.correlation_id')));
        $this->assertTrue((bool) Str::isUuid($segundaRespuesta->json('data.correlation_id')));
        $this->assertNotSame($primeraRespuesta->json('data.correlation_id'), $segundaRespuesta->json('data.correlation_id'));
        $this->assertCount(2, $ids);
    }

    public function test_pedido_inexistente_responde_404_sin_publicar(): void
    {
        $this->autenticar('CLIENTE', 15);
        $pedidos = Mockery::mock(PedidoRepositorioInterface::class);
        $pedidos->shouldReceive('buscarPorId')->once()->with(22)->andReturnNull();
        $this->app->instance(PedidoRepositorioInterface::class, $pedidos);
        $publicador = Mockery::mock(PublicadorConfirmacionPedidoInterface::class);
        $publicador->shouldNotReceive('publicar');
        $this->app->instance(PublicadorConfirmacionPedidoInterface::class, $publicador);

        $this->postJson(self::ENDPOINT)->assertNotFound()->assertJsonPath('codigo_error', 'PED_NO_ENCONTRADO');
    }

    public function test_falla_de_publicacion_responde_503_sin_aceptado(): void
    {
        $correlationId = 'c0684b68-3675-4542-bcef-f44265f58314';
        $this->autenticar('CLIENTE', 15);
        $this->registrarPedido(15);
        $publicador = Mockery::mock(PublicadorConfirmacionPedidoInterface::class);
        $publicador->shouldReceive('publicar')->once()->andThrow(new PublicacionConfirmacionFallida(null, $correlationId));
        $this->app->instance(PublicadorConfirmacionPedidoInterface::class, $publicador);

        $this->postJson(self::ENDPOINT, ['correlation_id' => $correlationId])
            ->assertServiceUnavailable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('codigo_error', 'RABBITMQ_UNAVAILABLE')
            ->assertJsonPath('correlation_id', $correlationId);
    }

    private function autenticar(string $rol, int $usuarioId = 15): void
    {
        $usuario = new UsuarioAutenticadoEnMemoria;
        $usuario->setRawAttributes(['id' => $usuarioId, 'rol_id' => $rol === 'ADMIN' ? 1 : 2], true);
        $usuario->setRelation('rol', new RolModelo(['nombre' => $rol]));

        $this->actingAs($usuario, 'sanctum');
    }

    private function registrarPedido(int $usuarioId): void
    {
        $pedido = new Pedido(
            id: 22,
            usuario_id: $usuarioId,
            fecha_entrega: '2026-12-01',
            estado: 'PENDIENTE',
            subtotal: '25.00',
            total: '25.00',
            creado_en: new DateTimeImmutable('2026-11-20T12:00:00+00:00'),
        );
        $pedidos = Mockery::mock(PedidoRepositorioInterface::class);
        $pedidos->shouldReceive('buscarPorId')->once()->with(22)->andReturn($pedido);
        $this->app->instance(PedidoRepositorioInterface::class, $pedidos);
    }
}

final class UsuarioAutenticadoEnMemoria extends UsuarioModelo
{
    public function load($relations): static
    {
        return $this;
    }
}
