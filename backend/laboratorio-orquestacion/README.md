# Práctica 3.3 – Orquestación y composición de capacidades

**Asignatura:** IS-803 – Integración e Implementación de Software  
**Proyecto:** NexoCommerce  
**Modalidad:** Práctica individual

## 1. Descripción

Esta práctica implementa un laboratorio de orquestación y composición de capacidades utilizando Laravel y dos servicios HTTP independientes.

El objetivo es consultar información de diferentes servicios y consolidarla en una única respuesta JSON, incorporando mecanismos de control de errores, tiempos de espera y trazabilidad mediante `X-Correlation-ID`.

La implementación se encuentra dentro de una copia individual del proyecto NexoCommerce y utiliza datos simulados para las pruebas.

## 2. Tecnologías utilizadas

- PHP 8.3
- Laravel
- API REST y JSON
- Postman
- Visual Studio Code
- PowerShell

## 3. Servicios implementados

**Servicio de usuarios**

- Puerto: `8001`
- Endpoint: `GET /usuarios/7`
- Respuesta:

```json
{
  "id": 7,
  "nombre": "Ana"
}
```

**Servicio de estados**

- Puerto: `8002`
- Endpoint: `GET /estados/101`
- Respuesta:

```json
{
  "solicitud_id": 101,
  "estado": "pendiente"
}
```

## 4. Orquestador Laravel

El orquestador consulta ambos servicios mediante solicitudes HTTP con un tiempo de espera máximo de tres segundos por petición.

Se implementaron dos modalidades:

**Orquestación estricta**

`GET /api/v1/laboratorio/resumen-pedido/101`

Cuando ambos servicios responden correctamente, devuelve una respuesta consolidada. Si alguna dependencia falla, devuelve un error controlado HTTP 502.

**Orquestación con respuesta parcial**

`GET /api/v1/laboratorio/resumen-pedido-parcial/101`

Permite devolver únicamente la información disponible cuando uno de los servicios deja de responder. Si ambos servicios están caídos, devuelve HTTP 502.

Esta segunda modalidad se implementó como demostración adicional de tolerancia a fallos.

## 5. Instrucciones de ejecución

### Requisitos

- PHP 8.3 o una versión compatible con el proyecto.
- Composer y dependencias Laravel instaladas.
- Configuración local del backend completada.
- Puertos `8000`, `8001` y `8002` disponibles.

### Configuración

Desde la carpeta `backend`, configurar en el archivo `.env`:

```dotenv
USERS_URL=http://127.0.0.1:8001
STATUS_URL=http://127.0.0.1:8002
```

Después ejecutar:

```powershell
php artisan config:clear
```

### Iniciar servicio de usuarios

Desde `backend`, ejecutar en una terminal:

```powershell
php -S 127.0.0.1:8001 .\laboratorio-orquestacion\servicios\usuarios.php
```

### Iniciar servicio de estados

En otra terminal:

```powershell
php -S 127.0.0.1:8002 .\laboratorio-orquestacion\servicios\estados.php
```

### Iniciar Laravel

En una tercera terminal:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Mantener las tres terminales abiertas durante las pruebas.

## 6. Pruebas realizadas

Las solicitudes se realizaron desde Postman utilizando:

```http
Accept: application/json
X-Correlation-ID: NEXO-PRUEBA-001
```

Se verificaron los siguientes escenarios:

| Escenario | Resultado |
|---|---|
| Ambos servicios activos | HTTP 200 y respuesta consolidada |
| Una dependencia caída, modo estricto | HTTP 502 y error controlado |
| Solo usuarios activo, modo parcial | HTTP 200 con datos del usuario |
| Solo estados activo, modo parcial | HTTP 200 con información del estado |
| Ambos servicios apagados, modo parcial | Respuesta de error controlada |

Para simular una falla, se detuvo temporalmente uno de los servidores HTTP mediante `Ctrl + C` y se repitió la petición desde Postman.

## 7. Manejo de errores y trazabilidad

El orquestador utiliza `Http::timeout(3)` para limitar la espera de las dependencias.

También genera o conserva el encabezado `X-Correlation-ID`, permitiendo identificar las operaciones realizadas.

Los fallos se registran mediante Laravel en:

`storage/logs/laravel.log`

Las respuestas de error no exponen trazas internas al cliente.

## 8. Archivos principales

```text
backend/
├── laboratorio-orquestacion/
│   ├── servicios/
│   │   ├── usuarios.php
│   │   └── estados.php
│   └── README.md
├── app/
│   └── Http/
│       └── Controllers/
│           └── Orquestacion/
│               └── ResumenPedidoController.php
├── config/
│   └── orquestacion.php
└── routes/
    └── api.php
```

## 9. Evidencias

Las evidencias de ejecución, el diagrama de secuencia y la explicación de las pruebas se encuentran en el informe PDF de la práctica.

## 10. Conclusión

La práctica permitió comprobar cómo un orquestador coordina servicios HTTP independientes para construir una respuesta unificada.

También se verificó el manejo controlado de errores, la trazabilidad mediante identificadores de correlación y el comportamiento de respuestas parciales cuando una dependencia deja de estar disponible.
