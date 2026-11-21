# Práctica individual 3.1 — Infraestructura de comunicación asíncrona

**Asignatura:** IS-803 — Integración e Implementación de Software
**Proyecto:** NexoCommerce
**Módulo:** Pedidos — confirmaciones asíncronas
**Rama individual:** `feat/melanie-comunicacion-asincrona-3-1`

Esta práctica implementa un productor Laravel, RabbitMQ y un consumidor Artisan independiente para procesar confirmaciones de pedidos. La respuesta `202 Accepted` significa que RabbitMQ confirmó la publicación; no significa que el consumidor ya terminó el procesamiento.

## 1. Proceso y arquitectura

La API autentica al cliente, verifica que el pedido le pertenezca y publica un evento en RabbitMQ. El consumidor independiente recibe el mensaje, valida el contrato, consulta el pedido y registra el resultado en PostgreSQL. La modalidad síncrona de laboratorio ejecuta la validación y consulta dentro de la solicitud HTTP para comparar latencias; no publica en RabbitMQ ni persiste un resultado.

```mermaid
flowchart LR
    A[Cliente / Postman] -->|POST autenticado| B[API Laravel / productor]
    B -->|evento JSON persistente| C[(RabbitMQ)]
    C --> Q[[pedidos.notificaciones]]
    Q --> D[Consumidor Artisan]
    D -->|resultado e identificadores| E[(PostgreSQL)]
    D -->|ACK tras completar la transacción| C
    D -->|rechazo sin reencolar| X[[pedidos.notificaciones.dlq]]
    C -.->|confirmación del publicador| B
    B -.->|202 Accepted| A
```

`message_id` identifica cada mensaje y permite aplicar idempotencia. `correlation_id` permite seguir una solicitud desde la API hasta el resultado del consumidor.

## 2. Componentes

| Componente | Implementación |
|---|---|
| Productor | Endpoint Laravel que valida la solicitud y publica con confirmación del broker. |
| Broker | RabbitMQ ejecutado con el Compose independiente de `backend/laboratorio-asincrono/`. |
| Cola principal | `pedidos.notificaciones`, durable y para mensajes persistentes. |
| Cola de mensajes rechazados | `pedidos.notificaciones.dlq`, enlazada mediante la configuración de dead lettering. |
| Consumidor | `php artisan laboratorio:consumir-confirmaciones-pedidos`; admite `--once`. |
| Persistencia | Tabla `laboratorio_procesamientos_confirmacion_pedido`; `message_id` es único. |
| Comparación síncrona | Endpoint de laboratorio que valida y consulta sin usar RabbitMQ ni registrar el resultado. |

## 3. Contrato JSON

El evento publicado tiene esta forma:

```json
{
  "message_id": "UUID",
  "correlation_id": "UUID",
  "tipo": "pedido.confirmacion.solicitada",
  "version": 1,
  "ocurrido_en": "fecha ISO 8601",
  "payload": {
    "pedido_id": 22
  }
}
```

El cuerpo HTTP es opcional. Si se proporciona `correlation_id`, debe ser un UUID válido; si se omite, la aplicación genera uno. Ejemplo válido:

```json
{
  "correlation_id": "0208dd65-cd90-48d6-be1d-702dbd407603"
}
```

Un valor inválido produce `422 Unprocessable Content`:

```json
{
  "correlation_id": "no-es-uuid"
}
```

No se incluyen datos personales en el mensaje. Las capturas y ejemplos publicados tampoco deben contener tokens ni credenciales.

## 4. Ejecución local

Requisitos: PHP compatible con el proyecto, Composer, PostgreSQL, Docker Compose y la extensión `php_sockets` habilitada para AMQP. Utiliza únicamente una base de datos local de pruebas. No uses la base compartida del equipo.

Desde `backend`, iniciar y consultar la infraestructura:

```powershell
docker compose -f .\laboratorio-asincrono\compose.yaml up -d
docker compose -f .\laboratorio-asincrono\compose.yaml ps
docker compose -f .\laboratorio-asincrono\compose.yaml exec -T rabbitmq rabbitmq-diagnostics -q ping
docker compose -f .\laboratorio-asincrono\compose.yaml exec -T rabbitmq rabbitmqctl list_queues name messages_ready messages_unacknowledged
```

Configura localmente las variables `RABBITMQ_*` descritas en [`backend/.env.example`](../../backend/.env.example), sin versionar el archivo `.env` ni imprimir sus valores. Antes de aplicar la migración aditiva de la práctica, verifica que la conexión activa corresponde a la base exclusiva de pruebas.

Solicitudes de Postman, suponiendo que Laravel escucha en `127.0.0.1:8000` y que el usuario autenticado es CLIENTE propietario del pedido de prueba:

**Productor asíncrono**

```http
POST http://127.0.0.1:8000/api/v1/laboratorio/pedidos/3/confirmaciones
Authorization: Bearer <TOKEN_LOCAL>
Accept: application/json
Content-Type: application/json
```

**Comparación síncrona**

```http
POST http://127.0.0.1:8000/api/v1/laboratorio/pedidos/3/confirmaciones-sincronas
Authorization: Bearer <TOKEN_LOCAL>
Accept: application/json
Content-Type: application/json
```

Para consumir una entrega:

```powershell
& "C:\tools\php83\php.exe" -d extension=php_sockets.dll artisan laboratorio:consumir-confirmaciones-pedidos --once
```

La ruta de PHP depende del equipo. El pedido `3` es un dato local de prueba y puede no existir en otra instalación.

## 5. Pruebas y evidencias manuales

Los resultados reportados para el laboratorio incluyen:

- **Mensaje válido:** productor respondió `202 Accepted`; luego el consumidor registró el evento como `PROCESADO`.
- **Consumidor detenido:** cuatro mensajes quedaron listos en la cola principal y cero sin ACK; al reanudar el consumidor, la cola principal quedó vacía.
- **Mensaje inválido:** `correlation_id` no válido produjo `422`; un evento inválido entregado al consumidor fue rechazado y llegó a la DLQ.
- **Correlación e idempotencia:** se verificó la persistencia de `message_id` y `correlation_id`; la tabla de laboratorio mostró cinco procesamientos con cinco identificadores de mensaje únicos.

Las capturas enviadas en la conversación muestran: respuesta síncrona `200`, estado de las colas en RabbitMQ, conteos de cola por CLI, registro de procesamiento en la base exclusiva de pruebas, respuesta `422` y consulta de registros para el mensaje inválido. Para que aparezcan en el repositorio y en GitHub, guarda las capturas revisadas (sin tokens ni secretos) en `docs/practica-3-1/evidencias/` y enlázalas aquí. Los archivos de imagen no se recibieron como archivos locales, así que no se han inventado ni agregado imágenes ficticias.

## 6. Comparación de latencia HTTP

| Indicador | Síncrona (laboratorio) | Asíncrona (productor) |
|---|---:|---:|
| Mediciones | 362, 395, 393 ms | 331, 698, 513 ms |
| Promedio | 383,33 ms | 514,00 ms |
| Mediana | 393 ms | 513 ms |
| Respuesta HTTP | `200 OK` al completar validación y consulta | `202 Accepted` tras confirmación del broker |
| Consumidor externo | No requerido | No requerido para aceptar publicaciones |

En esta muestra, la aceptación asíncrona tomó en promedio 130,67 ms más. Esto no demuestra que la arquitectura asíncrona sea más lenta en general: el productor espera una confirmación AMQP, mientras que el endpoint síncrono realiza una validación y consulta locales. No son mediciones equivalentes de extremo a extremo. Se comprobó el procesamiento posterior, pero no se midió su duración exacta desde la publicación.

## 7. Dificultad y solución

Se necesitaba demostrar que las solicitudes podían aceptarse mientras el consumidor estaba detenido y que los mensajes persistían hasta procesarse. Se detuvo el consumidor, se verificaron mensajes pendientes en RabbitMQ, se reanudó el procesamiento y se comprobó el resultado en PostgreSQL. La idempotencia se apoya en la restricción única de `message_id`.

## 8. Archivos relacionados

- Productor y contratos: `backend/app/Aplicacion/Notificaciones/` y los controladores y Request de `backend/app/Http/`.
- Consumidor: `backend/app/Console/Commands/ConsumirConfirmacionesPedidosCommand.php` y `backend/app/Infraestructura/Servicios/RabbitMqConfirmacionPedidoConsumer.php`.
- RabbitMQ: `backend/laboratorio-asincrono/`.
- Persistencia: migración, modelo y repositorio de `backend/` para `laboratorio_procesamientos_confirmacion_pedido`.
- Comparación síncrona: `ConfirmacionPedidoSincronaController.php` y su caso de uso.
- Pruebas: `backend/tests/Integracion/Notificaciones/` y `backend/tests/Unitarias/Notificaciones/`.

## 9. Publicación del repositorio

Antes de compartir el repositorio, verifica el commit, el push y el acceso al enlace. No publiques `.env`, tokens, claves, bases de datos locales, logs, `vendor/`, `node_modules/` ni configuraciones personales de herramientas.
