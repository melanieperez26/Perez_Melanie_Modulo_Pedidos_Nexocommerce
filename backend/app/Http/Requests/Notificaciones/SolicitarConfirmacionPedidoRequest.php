<?php

declare(strict_types=1);

namespace App\Http\Requests\Notificaciones;

use Illuminate\Foundation\Http\FormRequest;

final class SolicitarConfirmacionPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'correlation_id' => ['sometimes', 'uuid'],
        ];
    }
}
