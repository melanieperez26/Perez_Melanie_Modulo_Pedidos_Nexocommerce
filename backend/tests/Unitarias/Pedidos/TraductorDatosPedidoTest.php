<?php

declare(strict_types=1);

namespace Tests\Unitarias\Pedidos;

use App\Aplicacion\Pedidos\Traductores\TraductorDatosPedido;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TraductorDatosPedidoTest extends TestCase
{
    private TraductorDatosPedido $traductor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->traductor = new TraductorDatosPedido();
    }

    public function test_origen_web_genera_contrato_comun(): void
    {
        $resultado = $this->traductor->desdeWeb([
            'fechaEntrega' => '15/12/2026',
            'totalEsperado' => '15,50',
        ]);

        $this->assertSame([
            'fecha_entrega' => '2026-12-15',
            'total_esperado' => '15.50',
        ], $resultado);
    }

    public function test_origen_movil_genera_contrato_comun(): void
    {
        $resultado = $this->traductor->desdeMovil([
            'delivery_date' => '2026-12-15',
            'expected_total' => 15.50,
        ]);

        $this->assertSame([
            'fecha_entrega' => '2026-12-15',
            'total_esperado' => '15.50',
        ], $resultado);
    }

    public function test_ambos_origenes_generan_resultados_equivalentes(): void
    {
        $web = $this->traductor->desdeWeb([
            'fechaEntrega' => '15/12/2026',
            'totalEsperado' => '15,50',
        ]);

        $movil = $this->traductor->desdeMovil([
            'delivery_date' => '2026-12-15',
            'expected_total' => 15.50,
        ]);

        $this->assertSame($web, $movil);
    }

    public function test_rechaza_monto_web_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'El total web debe utilizar coma y dos decimales.'
        );

        $this->traductor->desdeWeb([
            'fechaEntrega' => '15/12/2026',
            'totalEsperado' => '15,50,30',
        ]);
    }

    public function test_rechaza_campo_obligatorio_faltante(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'El campo totalEsperado es obligatorio.'
        );

        $this->traductor->desdeWeb([
            'fechaEntrega' => '15/12/2026',
        ]);
    }

    public function test_rechaza_fecha_invalida(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'La fecha web debe tener el formato DD/MM/YYYY.'
        );

        $this->traductor->desdeWeb([
            'fechaEntrega' => '31/02/2026',
            'totalEsperado' => '15,50',
        ]);
    }

    public function test_rechaza_monto_movil_con_tres_decimales(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'El total móvil no puede tener más de dos decimales.'
        );

        $this->traductor->desdeMovil([
            'delivery_date' => '2026-12-15',
            'expected_total' => 15.555,
        ]);
    }
}
