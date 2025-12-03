# Services

Seaman supports a wide range of services for database, cache, development tools, search, and message queues.

## Databases

### PostgreSQL (Recommended)

**Default Version**: 16
**Port**: 5432

```yaml
services:
  postgresql:
    enabled: true
    type: "postgresql"
    version: "16"
    port: 5432
    environment:
      POSTGRES_DB: "myapp"
      POSTGRES_USER: "myapp"
      POSTGRES_PASSWORD: "secret"
```

**Features**:
- Advanced SQL features
- JSON/JSONB support
- Full-text search
- Excellent Doctrine integration

### MySQL

**Default Version**: 8.0
**Port**: 3306

```yaml
services:
  mysql:
    enabled: true
    type: "mysql"
    version: "8.0"
    port: 3306
    environment:
      MYSQL_DATABASE: "myapp"
      MYSQL_USER: "myapp"
      MYSQL_PASSWORD: "secret"
      MYSQL_ROOT_PASSWORD: "root"
```

### MariaDB

**Default Version**: 11.0
**Port**: 3306

```yaml
services:
  mariadb:
    enabled: true
    type: "mariadb"
    version: "11.0"
    port: 3306
    environment:
      MYSQL_DATABASE: "myapp"
      MYSQL_USER: "myapp"
      MYSQL_PASSWORD: "secret"
      MYSQL_ROOT_PASSWORD: "root"
```

### MongoDB

**Default Version**: 7.0
**Port**: 27017

```yaml
services:
  mongodb:
    enabled: true
    type: "mongodb"
    version: "7.0"
    port: 27017
    environment:
      MONGO_INITDB_DATABASE: "myapp"
      MONGO_INITDB_ROOT_USERNAME: "root"
      MONGO_INITDB_ROOT_PASSWORD: "secret"
```

**Use Cases**:
- Document-oriented data
- Flexible schemas
- High write throughput

## Cache & Session Storage

### Redis (Recommended)

**Default Version**: 7-alpine
**Port**: 6379

```yaml
services:
  redis:
    enabled: true
    type: "redis"
    version: "7-alpine"
    port: 6379
```

**Use Cases**:
- Session storage
- Cache backend
- Queue backend (Symfony Messenger)
- Real-time analytics

**Symfony Configuration**:
```yaml
# config/packages/cache.yaml
framework:
    cache:
        app: cache.adapter.redis
        default_redis_provider: redis://redis:6379
```

### Memcached

**Default Version**: latest
**Port**: 11211

```yaml
services:
  memcached:
    enabled: true
    type: "memcached"
    version: "latest"
    port: 11211
```

**Use Cases**:
- Simple key-value cache
- Session storage
- Lower memory footprint than Redis

## Development Tools

### Mailpit

**Default Version**: latest
**Ports**: 8025 (Web UI), 1025 (SMTP)

```yaml
services:
  mailpit:
    enabled: true
    type: "mailpit"
    version: "latest"
    ports:
      - "8025"  # Web UI
      - "1025"  # SMTP
```

**Features**:
- Captures all outgoing emails
- Web UI to view emails
- REST API
- No emails actually sent

**Access**: http://localhost:8025

**Symfony Configuration**:
```yaml
# config/packages/mailer.yaml
framework:
    mailer:
        dsn: smtp://mailpit:1025
```

### Dozzle

**Default Version**: latest
**Port**: 8080

```yaml
services:
  dozzle:
    enabled: true
    type: "dozzle"
    version: "latest"
    port: 8080
```

**Features**:
- Real-time log viewer for all containers
- Web-based interface
- Search and filter logs
- No agent installation required

**Access**: http://localhost:8080

### Minio

**Default Version**: latest
**Ports**: 9000 (API), 9001 (Console)

```yaml
services:
  minio:
    enabled: true
    type: "minio"
    version: "latest"
    ports:
      - "9000"   # API
      - "9001"   # Console
    environment:
      MINIO_ROOT_USER: "minio"
      MINIO_ROOT_PASSWORD: "minio123"
```

**Features**:
- S3-compatible object storage
- Perfect for testing file uploads
- Web console for management

**Access**:
- API: http://localhost:9000
- Console: http://localhost:9001

**Symfony Configuration**:
```yaml
# config/packages/oneup_flysystem.yaml
oneup_flysystem:
    adapters:
        minio_adapter:
            awss3v3:
                client: minio_client
                bucket: mybucket
    filesystems:
        minio:
            adapter: minio_adapter

services:
    minio_client:
        class: Aws\S3\S3Client
        arguments:
            -
                version: 'latest'
                region: 'us-east-1'
                endpoint: 'http://minio:9000'
                use_path_style_endpoint: true
                credentials:
                    key: 'minio'
                    secret: 'minio123'
```

## Search & Analytics

### Elasticsearch

**Default Version**: 8.11
**Port**: 9200

```yaml
services:
  elasticsearch:
    enabled: true
    type: "elasticsearch"
    version: "8.11"
    port: 9200
    environment:
      discovery.type: "single-node"
      xpack.security.enabled: "false"
```

**Use Cases**:
- Full-text search
- Log analytics
- Application search features

**Access**: http://localhost:9200

**Symfony Integration**:
```bash
composer require friendsofsymfony/elastica-bundle
```

## Message Queues

### RabbitMQ

**Default Version**: 3.13-management
**Ports**: 5672 (AMQP), 15672 (Management UI)

```yaml
services:
  rabbitmq:
    enabled: true
    type: "rabbitmq"
    version: "3.13-management"
    ports:
      - "5672"   # AMQP
      - "15672"  # Management UI
    environment:
      RABBITMQ_DEFAULT_USER: "guest"
      RABBITMQ_DEFAULT_PASS: "guest"
```

**Use Cases**:
- Asynchronous message processing
- Symfony Messenger transport
- Task queues
- Event-driven architecture

**Access**: http://localhost:15672 (guest/guest)

**Symfony Configuration**:
```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async: 'amqp://guest:guest@rabbitmq:5672/%2f/messages'
```

## Managing Services

### Adding Services

Use the interactive service addition:

```bash
seaman service:add
```

Or manually edit `.seaman/seaman.yaml` and regenerate Docker Compose:

```bash
seaman rebuild
```

### Removing Services

Interactive removal:

```bash
seaman service:remove
```

Or manually remove from `.seaman/seaman.yaml` and rebuild.

### Service Auto-Selection

Based on your project type, Seaman suggests default services during initialization:

| Project Type | Default Services |
|--------------|------------------|
| Web Application | Redis, Mailpit |
| API Platform | Redis |
| Microservice | Redis |
| Skeleton | None |

You can customize this selection during or after initialization.

## Persistent Data

Services with persistent data (databases, etc.) use Docker volumes defined in the `volumes` section:

```yaml
volumes:
  persist:
    - "postgresql"
    - "redis"
```

This ensures data survives container restarts. Use `seaman destroy` to remove volumes permanently.
