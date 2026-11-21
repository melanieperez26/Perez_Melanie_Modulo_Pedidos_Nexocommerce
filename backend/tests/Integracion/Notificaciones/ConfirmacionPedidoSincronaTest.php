<?php

declare(strict_types=1);

namespace Tests\Integracion\Notificaciones;

use App\Aplicacion\Notificaciones\Contratos\PublicadorConfirmacionPedidoInterface;
use App\Aplicacion\Notificaciones\Contratos\RegistroProcesamientoConfirmacionPedidoInterface;
use App\Dominio\Pedidos\Entidades\Pedido;
use App\Dominio\Pedidos\Repositorios\PedidoRepositorioInterface;
use App\Infraestructura\Persistencia\Eloquent\Modelos\RolModelo;
use App\Infraestructura\Persistencia\Eloquent\Modelos\UsuarioModelo;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

final class ConfirmacionPedidoSincronaTest extends TestCase
{
    private const string ENDPOINT = '/api/v1/laboratorio/pedidos/22/confirmaciones-sincronas';

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sin_autenticacion_responde_401_sin_consultar_pedido(): void
    {
        $pedidos = Mockery::mock(PedidoRepositorioInterface::class);
        $pedidos->shouldNotReceive('buscarPorId');
        $this->app->instance(PedidoRepositorioInterface::class, $pedidos);

        $this->postJson(self::ENDPOINT)->assertUnauthorized();
    }

    public function test_admin_responde_403_sin_consultar_pedido(): void
    {
        $this->autenticar('ADMIN');
        $pedidos = Mockery::mock(PedidoRepositorioInterface::class);
        $pedidos->shouldNotReceive('buscarPorId');
        $this->app->instance(PedidoRepositorioInterface::class, $pedidos);

        $this->postJson(self::ENDPOINT)->assertForbidden();
    }

    public function test_cliente_con_pedido_ajeno_responde_403(): void
    {
        $this->autenticar('CLIENTE', 15);
        $this->registrarPedido(99);

        $this->postJson(self::ENDPOINT)->assertForbidden()->assertJsonPath('codigo_error', 'PED_AJENO');
    }

    public function test_pedido_inexistente_responde_404(): void
    {
        $this->autenticar('CLIENTE');
        $pedidos = Mockery::mock(PedidoRepositorioInterface::class);
        $pedidos->shouldReceive('buscarPorId')->once()->with(22)->andReturnNull();
        $this->app->instance(PedidoRepositorioInterface::class, $pedidos);

        $this->postJson(self::ENDPOINT)->assertNotFound()->assertJsonPath('codigo_error', 'PED_NO_ENCONTRADO');
    }

    public function test_correlation_id_invalido_responde_422_sin_consultar_pedido(): void
    {
        $this->autenticar('CLIENTE');
        $pedidos = Mockery::mock(PedidoRepositorioInterface::class);
        $pedidos->shouldNotReceive('buscarPorId');
        $this->app->instance(PedidoRepositorioInterface::class, $pedidos);

        $this->postJson(self::ENDPOINT, ['correlation_id' => 'no-es-uuid'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['correlation_id']]);
    }

    public function test_cliente_propietario_recibe_200_sin_publicar_ni_persistir_resultados(): void
    {
        $this->autenticar('CLIENTE', 15);
        $this->registrarPedido(15);

        $publicador = Mockery::mock(PublicadorConfirmacionPedidoInterface::class);
        $publicador->shouldNotReceive('publicar');
        $this->app->instance(PublicadorConfirmacionPedidoInterface::class, $publicador);

        $registros = Mockery::mock(RegistroProcesamientoConfirmacionPedidoInterface::class);
        $registros->shouldNotReceive('registrarSiNuevo');
        $this->app->instance(RegistroProcesamientoConfirmacionPedidoInterface::class, $registros);

        $correlationId = '3e87aeb3-3ccb-44b7-9c5e-38410e010d1c';
        $response = $this->postJson(self::ENDPOINT, ['correlation_id' => $correlationId])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.correlation_id', $correlationId)
            ->assertJsonPath('data.modalidad', 'sincrona')
            ->assertJsonPath('data.resultado', 'pedido_validado_y_consultado')
            ->assertHeader('X-Correlation-ID', $correlationId);

        $this->assertTrue((bool) Str::isUuid($response->json('data.message_id')));
    }

    private function autenticar(string $rol, int $usuarioId = 15): void
    {
        $usuario = new UsuarioAutenticadoSincronoEnMemoria;
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

final class UsuarioAutenticadoSincronoEnMemoria extends UsuarioModelo
{
    public function load($relations): static
    {
        return $this;
    }
}
