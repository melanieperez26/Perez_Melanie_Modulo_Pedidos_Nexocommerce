<?php

declare(strict_types=1);

namespace Tests\Unitarias\Notificaciones;

use App\Aplicacion\Notificaciones\DTOs\MensajeConfirmacionPedidoDTO;
use App\Aplicacion\Notificaciones\Excepciones\MensajeConfirmacionPedidoInvalido;
use App\Aplicacion\Notificaciones\Servicios\ValidadorMensajeConfirmacionPedido;
use PHPUnit\Framework\TestCase;

final class ValidadorMensajeConfirmacionPedidoTest extends TestCase
{
    private const string MESSAGE_ID = 'e4095617-5fc8-4f0d-a2f1-098cdd367ea1';

    private const string CORRELATION_ID = '42a29a33-f750-44a0-81e3-50c393afad53';

    public function test_acepta_el_contrato_del_productor_y_devuelve_el_dto(): void
    {
        $mensaje = (new ValidadorMensajeConfirmacionPedido)->validar($this->cuerpo());

        self::assertEquals(new MensajeConfirmacionPedidoDTO(
            self::MESSAGE_ID,
            self::CORRELATION_ID,
            '2026-11-20T21:00:00+00:00',
            3,
        ), $mensaje);
    }

    public function test_rechaza_json_invalido(): void
    {
        $this->assertMensajeInvalido('{"message_id":');
    }

    public function test_rechaza_payload_vacio_sin_requerir_datos_de_pedido(): void
    {
        $datos = json_decode($this->cuerpo(), true, 512, JSON_THROW_ON_ERROR);
        $datos['payload'] = (object) [];

        $this->assertMensajeInvalido(json_encode($datos, JSON_THROW_ON_ERROR));
    }

    public function test_rechaza_los_campos_que_no_cumplen_el_contrato(): void
    {
        $casos = [];
        $datos = json_decode($this->cuerpo(), true, 512, JSON_THROW_ON_ERROR);
        $datos['campo_extra'] = true;
        $casos[] = $datos;

        $datos = json_decode($this->cuerpo(), true, 512, JSON_THROW_ON_ERROR);
        $datos['message_id'] = 'invalido';
        $casos[] = $datos;

        $datos = json_decode($this->cuerpo(), true, 512, JSON_THROW_ON_ERROR);
        $datos['correlation_id'] = 'invalido';
        $casos[] = $datos;

        $datos = json_decode($this->cuerpo(), true, 512, JSON_THROW_ON_ERROR);
        $datos['tipo'] = 'evento.desconocido';
        $casos[] = $datos;

        $datos = json_decode($this->cuerpo(), true, 512, JSON_THROW_ON_ERROR);
        $datos['ocurrido_en'] = 'fecha-invalida';
        $casos[] = $datos;

        $datos = json_decode($this->cuerpo(), true, 512, JSON_THROW_ON_ERROR);
        $datos['payload']['pedido_id'] = '3';
        $casos[] = $datos;

        $datos = json_decode($this->cuerpo(), true, 512, JSON_THROW_ON_ERROR);
        $datos['payload']['pedido_id'] = 0;
        $casos[] = $datos;

        foreach ($casos as $caso) {
            $this->assertMensajeInvalido(json_encode($caso, JSON_THROW_ON_ERROR));
        }
    }

    public function test_conserva_message_id_valido_en_el_error_del_contrato(): void
    {
        $datos = json_decode($this->cuerpo(), true, 512, JSON_THROW_ON_ERROR);
        $datos['version'] = '1';

        try {
            (new ValidadorMensajeConfirmacionPedido)->validar(json_encode($datos, JSON_THROW_ON_ERROR));
            self::fail('Se esperaba un error de validacion.');
        } catch (MensajeConfirmacionPedidoInvalido $exception) {
            self::assertSame(self::MESSAGE_ID, $exception->messageId);
        }
    }

    private function assertMensajeInvalido(string $cuerpo): void
    {
        try {
            (new ValidadorMensajeConfirmacionPedido)->validar($cuerpo);
            self::fail('Se esperaba un error de validacion.');
        } catch (MensajeConfirmacionPedidoInvalido) {
            self::assertTrue(true);
        }
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
