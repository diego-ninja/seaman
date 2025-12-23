# Example Plugins

This directory contains example plugins that demonstrate Seaman's plugin system.

## Available Examples

### ClickHouse Plugin

A complete example plugin that demonstrates all extension points:

- **Custom Service**: Adds ClickHouse OLAP database support
- **Custom Command**: `clickhouse:query` for executing SQL queries
- **Lifecycle Hooks**: Messages on start/destroy
- **Configuration Schema**: Customizable version, ports, credentials

#### Installation

Copy the plugin to your project's `.seaman/plugins/` directory:

```bash
cp -r examples/plugins/clickhouse /path/to/your/project/.seaman/plugins/
```

Then configure it in your `seaman.yaml`:

```yaml
services:
  clickhouse:
    enabled: true
    type: custom
    port: 8123

plugins:
  seaman/clickhouse-plugin:
    version: "24.8"
    database: "analytics"
```

## Creating Your Own Plugin

See the [Plugin Documentation](../../docs/plugins.md) for a complete guide on creating plugins.
