<?php

declare(strict_types=1);

namespace App\Aplicacion\Notificaciones\Excepciones;

use RuntimeException;

final class PublicacionConfirmacionFallida extends RuntimeException
{
    public function __construct(
        public readonly ?string $message_id = null,
        public readonly ?string $correlation_id = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct('No se pudo confirmar la publicacion del mensaje.', 0, $previous);
    }
}
