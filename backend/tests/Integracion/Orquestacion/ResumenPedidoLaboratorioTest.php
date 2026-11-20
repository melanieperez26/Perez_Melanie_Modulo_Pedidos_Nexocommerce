<?php

declare(strict_types=1);

namespace Tests\Integracion\Orquestacion;

use App\Infraestructura\Persistencia\Eloquent\Modelos\UsuarioModelo;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResumenPedidoLaboratorioTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $rutas = [
        '/api/v1/laboratorio/resumen-pedido/101',
        '/api/v1/laboratorio/resumen-pedido-parcial/101',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        config([
            'orquestacion.usuarios_url' => 'http://usuarios-servicio.test',
            'orquestacion.estados_url' => 'http://estados-servicio.test',
        ]);

        Http::fake([
            'http://usuarios-servicio.test/usuarios/7' => Http::response([
                'id' => 7,
                'nombre' => 'Usuario de prueba',
            ]),
            'http://estados-servicio.test/estados/101' => Http::response([
                'solicitud_id' => 101,
                'estado' => 'pendiente',
            ]),
        ]);
    }

    public function test_ambas_rutas_requieren_autenticacion(): void
    {
        foreach ($this->rutas as $ruta) {
            $this->getJson($ruta)->assertUnauthorized();
        }
    }

    public function test_ambas_rutas_rechazan_a_usuarios_con_rol_cliente(): void
    {
        $cliente = UsuarioModelo::factory()->verificado()->create();
        $this->withToken($cliente->createToken('test')->plainTextToken);

        foreach ($this->rutas as $ruta) {
            $this->getJson($ruta)->assertForbidden();
        }
    }

    public function test_admin_puede_consultar_ambas_rutas_cuando_las_dependencias_responden(): void
    {
        $admin = UsuarioModelo::factory()->admin()->create();
        $this->withToken($admin->createToken('test')->plainTextToken);

        $this->getJson($this->rutas[0])
            ->assertSuccessful()
            ->assertJsonPath('pedido_id', 101)
            ->assertJsonPath('usuario.id', 7)
            ->assertJsonPath('estado', 'pendiente');

        $this->getJson($this->rutas[1])
            ->assertSuccessful()
            ->assertJsonPath('pedido_id', 101)
            ->assertJsonPath('usuario.id', 7)
            ->assertJsonPath('estado', 'pendiente');
    }
}
