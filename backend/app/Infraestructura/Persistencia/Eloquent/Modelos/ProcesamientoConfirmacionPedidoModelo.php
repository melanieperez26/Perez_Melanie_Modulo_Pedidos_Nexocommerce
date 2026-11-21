<?php

declare(strict_types=1);

namespace App\Infraestructura\Persistencia\Eloquent\Modelos;

use Illuminate\Database\Eloquent\Model;

final class ProcesamientoConfirmacionPedidoModelo extends Model
{
    protected $table = 'laboratorio_procesamientos_confirmacion_pedido';

    public $timestamps = false;

    protected $guarded = [];
}
