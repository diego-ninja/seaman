# Services

Seaman supports databases, caches, message queues, search engines, and development tools.

## Databases

### PostgreSQL

```yaml
services:
  postgresql:
    enabled: true
    type: postgresql
    version: "16"
    port: 5432
    environment:
      POSTGRES_DB: app
      POSTGRES_USER: app
      POSTGRES_PASSWORD: secret
```

Default port: 5432 | [Official docs](https://hub.docker.com/_/postgres)

### MySQL

```yaml
services:
  mysql:
    enabled: true
    type: mysql
    version: "8.0"
    port: 3306
    environment:
      MYSQL_DATABASE: app
      MYSQL_USER: app
      MYSQL_PASSWORD: secret
      MYSQL_ROOT_PASSWORD: root
```

Default port: 3306 | [Official docs](https://hub.docker.com/_/mysql)

### MariaDB

```yaml
services:
  mariadb:
    enabled: true
    type: mariadb
    version: "11.0"
    port: 3306
    environment:
      MYSQL_DATABASE: app
      MYSQL_USER: app
      MYSQL_PASSWORD: secret
      MYSQL_ROOT_PASSWORD: root
```

Default port: 3306 | [Official docs](https://hub.docker.com/_/mariadb)

### MongoDB

```yaml
services:
  mongodb:
    enabled: true
    type: mongodb
    version: "7.0"
    port: 27017
    environment:
      MONGO_INITDB_DATABASE: app
      MONGO_INITDB_ROOT_USERNAME: root
      MONGO_INITDB_ROOT_PASSWORD: secret
```

Default port: 27017 | [Official docs](https://hub.docker.com/_/mongo)

### SQLite

SQLite doesn't require a container. Seaman creates a SQLite file in your project.

```yaml
services:
  sqlite:
    enabled: true
    type: sqlite
```

## Cache

### Redis

```yaml
services:
  redis:
    enabled: true
    type: redis
    version: "7-alpine"
    port: 6379
```

Default port: 6379 | [Official docs](https://hub.docker.com/_/redis)

Symfony configuration:
```yaml
# config/packages/cache.yaml
framework:
    cache:
        app: cache.adapter.redis
        default_redis_provider: redis://redis:6379
```

### Valkey

Redis-compatible fork:

```yaml
services:
  valkey:
    enabled: true
    type: valkey
    version: "7-alpine"
    port: 6379
```

Default port: 6379 | [Official docs](https://hub.docker.com/r/valkey/valkey)

### Memcached

```yaml
services:
  memcached:
    enabled: true
    type: memcached
    version: "latest"
    port: 11211
```

Default port: 11211 | [Official docs](https://hub.docker.com/_/memcached)

## Message Queues

### RabbitMQ

```yaml
services:
  rabbitmq:
    enabled: true
    type: rabbitmq
    version: "3.13-management"
    port: 5672
    additional_ports:
      - 15672
    environment:
      RABBITMQ_DEFAULT_USER: guest
      RABBITMQ_DEFAULT_PASS: guest
```

Ports: 5672 (AMQP), 15672 (Management UI) | [Official docs](https://hub.docker.com/_/rabbitmq)

Management UI: http://localhost:15672 (guest/guest)

Symfony configuration:
```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async: amqp://guest:guest@rabbitmq:5672/%2f/messages
```

### Kafka

```yaml
services:
  kafka:
    enabled: true
    type: kafka
    version: "latest"
    port: 9092
```

Default port: 9092 | [Official docs](https://hub.docker.com/r/bitnami/kafka)

## Search

### Elasticsearch

```yaml
services:
  elasticsearch:
    enabled: true
    type: elasticsearch
    version: "8.11"
    port: 9200
    environment:
      discovery.type: single-node
      xpack.security.enabled: "false"
```

Default port: 9200 | [Official docs](https://hub.docker.com/_/elasticsearch)

### OpenSearch

```yaml
services:
  opensearch:
    enabled: true
    type: opensearch
    version: "2.11"
    port: 9200
    environment:
      discovery.type: single-node
      DISABLE_SECURITY_PLUGIN: "true"
```

Default port: 9200 | [Official docs](https://hub.docker.com/r/opensearchproject/opensearch)

## Development Tools

### Mailpit

Email testing tool that catches all outgoing emails.

```yaml
services:
  mailpit:
    enabled: true
    type: mailpit
    version: "latest"
    port: 8025
    additional_ports:
      - 1025
```

Ports: 8025 (Web UI), 1025 (SMTP) | [Official docs](https://github.com/axllent/mailpit)

Web UI: http://localhost:8025

Symfony configuration:
```yaml
# config/packages/mailer.yaml
framework:
    mailer:
        dsn: smtp://mailpit:1025
```

### Minio

S3-compatible object storage.

```yaml
services:
  minio:
    enabled: true
    type: minio
    version: "latest"
    port: 9000
    additional_ports:
      - 9001
    environment:
      MINIO_ROOT_USER: minio
      MINIO_ROOT_PASSWORD: minio123
```

Ports: 9000 (API), 9001 (Console) | [Official docs](https://hub.docker.com/r/minio/minio)

Console: http://localhost:9001

### Dozzle

Real-time log viewer for all containers.

```yaml
services:
  dozzle:
    enabled: true
    type: dozzle
    version: "latest"
    port: 8080
```

Default port: 8080 | [Official docs](https://github.com/amir20/dozzle)

Web UI: http://localhost:8080

## Proxy

### Traefik

Reverse proxy with automatic HTTPS for local development.

```yaml
proxy:
  enabled: true
  domain_prefix: myapp
  cert_resolver: default
  dashboard: true
```

When enabled, services are available at `https://<service>.myapp.localhost`.

See [Configuration](configuration.md#proxy) for details.

## Managing Services

### Add a Service

Interactive:
```bash
seaman service:add
```

Or edit `.seaman/seaman.yaml` and rebuild:
```bash
seaman rebuild
```

### Remove a Service

Interactive:
```bash
seaman service:remove
```

Or remove from `.seaman/seaman.yaml` and rebuild.

### List Services

```bash
seaman service:list
```

## Data Persistence

Database data persists across container restarts using Docker volumes.

Configure which services persist data:

```yaml
volumes:
  persist:
    - postgresql
    - redis
```

To remove all data permanently:

```bash
seaman destroy
```

## Port Conflicts

If a port is already in use, Seaman will:
1. Detect the conflict at startup
2. Suggest an alternative port
3. Ask for confirmation before proceeding

You can also change ports in `.seaman/seaman.yaml`:

```yaml
services:
  postgresql:
    port: 5433  # Use 5433 instead of 5432
```
