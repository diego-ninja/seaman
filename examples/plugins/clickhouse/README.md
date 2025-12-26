# ClickHouse Plugin for Seaman

This plugin adds [ClickHouse](https://clickhouse.com/) OLAP database support to Seaman.

## Features

- **ClickHouse Service**: Fast column-oriented database optimized for analytics
- **Query Command**: Execute SQL queries directly from the CLI
- **Lifecycle Hooks**: Helpful messages on start/destroy

## Installation

This plugin is installed locally in `.seaman/plugins/clickhouse/`.

To enable it, add `clickhouse` to your services in `seaman.yaml`:

```yaml
services:
  clickhouse:
    enabled: true
    type: custom
```

## Configuration

Configure the plugin in your `seaman.yaml`:

```yaml
plugins:
  seaman/clickhouse-plugin:
    version: "24.8"        # ClickHouse version (default: 24.8)
    user: "default"        # Database user (default: default)
    password: ""           # Database password (default: empty)
    database: "default"    # Default database (default: default)
    http_port: 8123        # HTTP API port (default: 8123)
    native_port: 9000      # Native protocol port (default: 9000)
    enable_backups: true   # Show backup warnings on destroy (default: true)
```

## Usage

### Start ClickHouse

```bash
seaman start
```

### Execute Queries

```bash
# Interactive shell
seaman clickhouse:query

# Single query
seaman clickhouse:query "SELECT version()"

# Query with specific format
seaman clickhouse:query "SELECT * FROM system.tables" --format=JSON

# Query specific database
seaman clickhouse:query "SHOW TABLES" --database=mydb
```

### Available Output Formats

- `TabSeparated` - Tab-separated values
- `CSV` - Comma-separated values
- `JSON` - Full JSON output
- `JSONEachRow` - One JSON object per line
- `Pretty` - Formatted table
- `PrettyCompact` - Compact formatted table (default)
- `Vertical` - Each column on its own line

## Ports

| Port | Protocol | Description |
|------|----------|-------------|
| 8123 | HTTP | HTTP API and web interface |
| 9000 | Native | Native ClickHouse protocol |

## Volumes

- `clickhouse_data` - Database data files
- `clickhouse_logs` - Server logs

## Example Queries

```sql
-- Check version
SELECT version()

-- List databases
SHOW DATABASES

-- Create a table
CREATE TABLE events (
    timestamp DateTime,
    event_type String,
    user_id UInt64,
    data String
) ENGINE = MergeTree()
ORDER BY timestamp

-- Insert data
INSERT INTO events VALUES
    (now(), 'click', 123, '{"page": "/home"}'),
    (now(), 'view', 456, '{"page": "/products"}')

-- Query data
SELECT * FROM events ORDER BY timestamp DESC LIMIT 10
```

## Links

- [ClickHouse Documentation](https://clickhouse.com/docs)
- [SQL Reference](https://clickhouse.com/docs/en/sql-reference)
- [Docker Image](https://hub.docker.com/r/clickhouse/clickhouse-server)
