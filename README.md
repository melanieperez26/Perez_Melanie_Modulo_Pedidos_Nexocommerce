# NexoCommerce – Práctica individual

## 1. Descripción del proyecto

NexoCommerce es un sistema de comercio electrónico modular desarrollado como proyecto académico de la asignatura **IS-803 – Integración e Implementación de Software**, de la Universidad Laica Eloy Alfaro de Manabí (ULEAM), durante el periodo 2026-2.

El sistema utiliza una arquitectura distribuida. Su backend está desarrollado con Laravel y expone una API REST que permite la comunicación con los clientes web y móvil.

Este repositorio contiene una **copia independiente del proyecto base desarrollado en equipo**, destinada al desarrollo y presentación de la práctica individual sobre DTO y contratos de intercambio JSON.

## 2. Alcance de la práctica individual

La práctica se centra en el proceso de creación de pedidos mediante el uso de DTO (Data Transfer Object) y contratos de intercambio JSON.

El trabajo individual comprende:

- Implementación del DTO `CrearPedidoDTO`.
- Recepción y validación de los datos enviados mediante JSON.
- Integración del proceso de creación de pedidos con el carrito de compras.
- Separación de responsabilidades mediante el caso de uso `CrearPedidoDesdeCarrito`.
- Estructuración de la respuesta HTTP mediante `PedidoResumenResource`.
- Validación de autenticación, permisos y datos de entrada.
- Comprobación del comportamiento mediante pruebas automatizadas y solicitudes HTTP.

**Importante:** el resto de los módulos pertenecen al proyecto base desarrollado colaborativamente. Su inclusión permite ejecutar y probar el módulo individual dentro de su contexto funcional, pero no implica que hayan sido desarrollados individualmente para esta práctica.

## 3. Tecnologías utilizadas

| Componente | Tecnología |
|---|---|
| Backend | Laravel |
| Lenguaje backend | PHP 8.3 |
| API | REST / JSON |
| Autenticación | Laravel Sanctum |
| Base de datos | PostgreSQL |
| Pruebas | PHPUnit |
| Cliente web | React, TypeScript y Vite |
| Aplicación móvil | Kotlin y Jetpack Compose |
| Infraestructura | Docker y NGINX |
| Control de versiones | Git y GitHub |

## 4. Estructura del proyecto

```text
NexoCommerce/
├── app-movil/
├── backend/
│   ├── app/
│   │   ├── Aplicacion/
│   │   │   └── Pedidos/
│   │   │       ├── CasosUso/
│   │   │       └── DTOs/
│   │   ├── Dominio/
│   │   ├── Http/
│   │   └── Infraestructura/
│   ├── database/
│   ├── routes/
│   └── tests/
├── database/
├── docs/
├── frontend-web/
├── nginx/
├── scripts/
├── servidor-archivos/
├── tests/
├── .gitignore
└── README.md
```

## 5. Funcionamiento del módulo individual

El proceso de creación de pedidos recibe una solicitud autenticada a través del endpoint:

`POST /api/v1/pedidos`

Ejemplo de solicitud JSON:

```json
{
  "fecha_entrega": "2026-11-15",
  "total_esperado": "5.00"
}
```

El proceso contempla:

1. Recepción de la solicitud HTTP.
2. Verificación de autenticación y permisos del usuario.
3. Validación de los datos de entrada.
4. Transferencia de los datos mediante `CrearPedidoDTO`.
5. Ejecución del caso de uso `CrearPedidoDesdeCarrito`.
6. Persistencia del pedido y sus detalles.
7. Construcción de la respuesta mediante `PedidoResumenResource`.

### Respuestas HTTP contempladas

| Código | Resultado |
|---|---|
| 201 | Pedido creado correctamente |
| 401 | Usuario no autenticado |
| 403 | Usuario sin permisos |
| 422 | Datos de entrada inválidos |

## 6. Requisitos de ejecución

Para trabajar con el backend se requiere:

- PHP 8.3.
- Composer.
- PostgreSQL.
- Una base de datos configurada para el proyecto.

Para ejecutar los clientes web y móvil se requieren sus respectivas herramientas de desarrollo.

## 7. Configuración del backend

Desde la carpeta `backend`:

1. Instalar las dependencias:

   ```bash
   composer install
   ```

2. Crear el archivo de entorno a partir de `.env.example` y configurar los datos de conexión a una base de datos local.

3. Generar la clave de aplicación:

   ```bash
   php artisan key:generate
   ```

4. Ejecutar las migraciones sobre la base de datos configurada:

   ```bash
   php artisan migrate
   ```

5. Iniciar el servidor de desarrollo:

   ```bash
   php artisan serve
   ```

No se deben compartir archivos `.env`, contraseñas ni credenciales de acceso.

## 8. Ejecución de pruebas

Las pruebas del backend se ejecutan mediante PHPUnit:

```bash
php artisan test
```

Es necesario configurar previamente el entorno de pruebas para que utilice una base de datos independiente. No se deben ejecutar pruebas sobre la base de datos de desarrollo o sobre una base de datos compartida.

La implementación individual fue comprobada mediante pruebas de validación, autenticación, creación de pedidos y persistencia.

## 9. Propósito del repositorio

Este repositorio tiene fines académicos y permite mantener una versión independiente del proyecto base para desarrollar, probar y presentar el aporte individual correspondiente a la práctica:

**Práctica 2.2 – DTO y contratos de intercambio JSON.**

**Universidad Laica Eloy Alfaro de Manabí (ULEAM)**  
**IS-803 – Integración e Implementación de Software**  
**Periodo académico 2026-2**