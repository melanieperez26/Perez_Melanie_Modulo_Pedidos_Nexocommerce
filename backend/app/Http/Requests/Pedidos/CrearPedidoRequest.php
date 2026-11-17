<?php

declare(strict_types=1);

namespace App\Http\Requests\Pedidos;

use App\Aplicacion\Pedidos\Traductores\TraductorDatosPedido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CrearPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $origen = $this->header('X-Formato-Origen');

        // Sin encabezado: conservar el contrato original.
        if ($origen === null) {
            return;
        }

        $traductor = app(TraductorDatosPedido::class);

        try {
            $datos = match ($origen) {
                'web' => $traductor->desdeWeb($this->all()),
                'movil' => $traductor->desdeMovil($this->all()),
                default => throw new InvalidArgumentException(
                    'Formato de origen no reconocido.'
                ),
            };

            // Sustituir la entrada por el contrato común.
            $this->replace($datos);

        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'formato_origen' => [$e->getMessage()],
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'fecha_entrega' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
            'total_esperado' => [
                'required',
                'string',
                'max:11',
                'regex:/^(?:0|[1-9]\d{0,7})\.\d{2}$/',
            ],
        ];
    }
}
