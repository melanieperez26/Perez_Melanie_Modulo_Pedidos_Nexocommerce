# Messaging infrastructure for Practice 3.1

This Compose project is independent of the existing backend Compose files. It
defines RabbitMQ only; it does not include the Laravel producer or consumer.

## Requirements and credentials

- Docker Desktop with Docker Compose v2.
- Local ports `5672` (AMQP) and `15672` (management UI) available.
- `RABBITMQ_DEFAULT_USER` and `RABBITMQ_DEFAULT_PASS` supplied to the current
  PowerShell process. Compose fails before creating the container if either is
  missing.

Do not store these credentials in this repository or in a versioned `.env`
file. In a new PowerShell window, enter them interactively:

```powershell
$env:RABBITMQ_DEFAULT_USER = Read-Host -Prompt 'RabbitMQ admin username'
$securePassword = Read-Host -Prompt 'RabbitMQ admin password' -AsSecureString
$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
try {
    $env:RABBITMQ_DEFAULT_PASS = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
}
finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    $securePassword.Dispose()
}
```

The variables are limited to that PowerShell window and inherited by Docker
Compose. Do not type the password in a literal command or capture the prompt.

## First start on a new, empty RabbitMQ data volume

Run from `backend`. Definitions are intentionally imported after RabbitMQ's
first boot: boot-time definition import on a blank node prevents RabbitMQ from
creating its default user and virtual host. The Compose environment variables
seed that user and `/` vhost on the first boot; RabbitMQ grants the initial
user its default administrator tag and full permissions.

```powershell
Get-NetTCPConnection -State Listen -LocalPort 5672,15672 -ErrorAction SilentlyContinue
docker ps --format 'table {{.Names}} {{.Ports}}'
docker compose -f .\laboratorio-asincrono\compose.yaml config --quiet
docker compose -f .\laboratorio-asincrono\compose.yaml up -d
docker compose -f .\laboratorio-asincrono\compose.yaml ps
docker compose -f .\laboratorio-asincrono\compose.yaml exec -T rabbitmq rabbitmq-diagnostics -q ping
docker compose -f .\laboratorio-asincrono\compose.yaml exec -T rabbitmq rabbitmqctl import_definitions /etc/rabbitmq/definitions.json
```

The imported definitions declare `/`, the durable queues
`pedidos.notificaciones` and `pedidos.notificaciones.dlq`, a durable direct
dead-letter exchange, its DLQ binding, and a policy routing rejected messages
there when they are rejected without requeue. The import can be repeated; it
merges definitions and preserves unrelated broker data.

Confirm the resulting state without printing credentials:

```powershell
docker compose -f .\laboratorio-asincrono\compose.yaml exec -T rabbitmq rabbitmqctl list_users
docker compose -f .\laboratorio-asincrono\compose.yaml exec -T rabbitmq rabbitmqctl list_vhosts name
docker compose -f .\laboratorio-asincrono\compose.yaml exec -T rabbitmq rabbitmqctl list_permissions -p /
docker compose -f .\laboratorio-asincrono\compose.yaml exec -T rabbitmq rabbitmqctl list_queues name durable messages_ready
```

The management UI is at `http://127.0.0.1:15672`; AMQP listens at
`127.0.0.1:5672`.

## Repair an already initialized volume with no users or vhosts

Changing `RABBITMQ_DEFAULT_USER`, `RABBITMQ_DEFAULT_PASS`, or
`RABBITMQ_DEFAULT_VHOST` does not recreate broker records in an initialized
volume. Do not delete or reset that volume. With the running service, and after
confirming that the user is absent, create it interactively so RabbitMQ prompts
for the password instead of placing it in command history:

```powershell
docker compose -f .\laboratorio-asincrono\compose.yaml exec rabbitmq rabbitmqctl list_users
docker compose -f .\laboratorio-asincrono\compose.yaml exec rabbitmq rabbitmqctl add_user $env:RABBITMQ_DEFAULT_USER
docker compose -f .\laboratorio-asincrono\compose.yaml exec -T rabbitmq rabbitmqctl set_user_tags $env:RABBITMQ_DEFAULT_USER administrator
docker compose -f .\laboratorio-asincrono\compose.yaml exec -T rabbitmq rabbitmqctl add_vhost /
docker compose -f .\laboratorio-asincrono\compose.yaml exec -T rabbitmq rabbitmqctl set_permissions -p / $env:RABBITMQ_DEFAULT_USER ".*" ".*" ".*"
docker compose -f .\laboratorio-asincrono\compose.yaml exec -T rabbitmq rabbitmqctl import_definitions /etc/rabbitmq/definitions.json
```

The `add_user` command requires an interactive terminal and prompts for the
password. If that user already exists, do not rerun `add_user`; inspect the
account and use a deliberate password recovery procedure if needed. If `/`
already exists, do not recreate it; continue with permission verification and
the definitions import. These commands modify only this Compose project's
RabbitMQ data, and are not run by this guide automatically.

## Stop only this infrastructure

```powershell
docker compose -f .\laboratorio-asincrono\compose.yaml down
```

This stops and removes resources belonging to this Compose project and keeps
its persistent volume. Do not add `-v` if the queues and messages must remain.
Close the PowerShell window when finished to discard its credential variables.
