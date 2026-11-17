<?php

declare(strict_types=1);

namespace Tests\Integracion\Pedidos;

use App\Infraestructura\Persistencia\Eloquent\Modelos\DetalleModelo;
use App\Infraestructura\Persistencia\Eloquent\Modelos\ProductoModelo;
use App\Infraestructura\Persistencia\Eloquent\Modelos\UsuarioModelo;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TraduccionPedidosHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    private function prepararCarrito(): void
    {
        $cliente = UsuarioModelo::factory()
            ->verificado()
            ->create();

        $this->withToken(
            $cliente->createToken('test')->plainTextToken
        );

        $producto = ProductoModelo::factory()->create([
            'nombre' => 'Caja regalo de prueba',
            'precio_base' => '15.50',
        ]);

        DetalleModelo::factory()->create([
            'producto_id' => $producto->id,
            'stock' => 5,
        ]);

        $this->postJson('/api/v1/carrito/items', [
            'producto_id' => $producto->id,
            'cantidad' => 1,
        ])->assertCreated();
    }

    public function test_cliente_web_crea_pedido_traducido(): void
    {
        $this->prepararCarrito();

        $fecha = now()->addDays(7);

        $this->withHeader('X-Formato-Origen', 'web')
            ->postJson('/api/v1/pedidos', [
                'fechaEntrega' => $fecha->format('d/m/Y'),
                'totalEsperado' => '15,50',
            ])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'PENDIENTE')
            ->assertJsonPath('data.total', 15.5);

        $this->assertDatabaseCount('pedidos', 1);
    }

    public function test_cliente_movil_crea_pedido_traducido(): void
    {
        $this->prepararCarrito();

        $this->withHeader('X-Formato-Origen', 'movil')
            ->postJson('/api/v1/pedidos', [
                'delivery_date' => now()->addDays(7)->toDateString(),
                'expected_total' => 15.50,
            ])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'PENDIENTE')
            ->assertJsonPath('data.total', 15.5);

        $this->assertDatabaseCount('pedidos', 1);
    }

    public function test_rechaza_monto_web_invalido(): void
    {
        $this->prepararCarrito();

        $this->withHeader('X-Formato-Origen', 'web')
            ->postJson('/api/v1/pedidos', [
                'fechaEntrega' => now()->addDays(7)->format('d/m/Y'),
                'totalEsperado' => '15,50,30',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['formato_origen']);

        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_contrato_original_sigue_funcionando(): void
    {
        $this->prepararCarrito();

        $this->postJson('/api/v1/pedidos', [
            'fecha_entrega' => now()->addDays(7)->toDateString(),
            'total_esperado' => '15.50',
        ])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'PENDIENTE')
            ->assertJsonPath('data.total', 15.5);

        $this->assertDatabaseCount('pedidos', 1);
    }
}
