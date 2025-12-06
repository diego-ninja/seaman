# Table Widget Design

## Overview

Create an advanced Table widget for terminal output that supports:
- Header section with full-width lines (no columns)
- Column headers
- Multi-line cells
- Row separators
- Unicode box-drawing characters with colors

## Structure

```
src/UI/Widget/Table/
├── Table.php           # Main prompt class
└── TableRenderer.php   # Rendering logic
```

## API

```php
use Seaman\UI\Widget\Table\Table;

$table = new Table(
    headerLines: [
        'Project: my-app ~/Code/my-app',
        'PHP: 8.4 | Xdebug: enabled | Proxy: Traefik',
    ],
    headers: ['SERVICE', 'STATUS', 'URL', 'INFO'],
    rows: [
        ['app', 'running', 'https://app.local', 'PHP 8.4'],
        Table::separator(),
        ['db', 'stopped', 'localhost:5432', 'PostgreSQL 16'],
    ],
);

$table->display();
```

Multi-line cells use arrays:
```php
['web', 'running', ['https://app.local', 'InDocker:', '  - web:80'], 'PHP 8.4']
```

## Output

```
┌──────────────────────────────────────────────────────────────────────────┐
│ Project: my-app ~/Code/my-app                                            │
│ PHP: 8.4 | Xdebug: enabled | Proxy: Traefik                              │
├──────────────┬─────────────┬─────────────────────────────┬───────────────┤
│ SERVICE      │ STATUS      │ URL                         │ INFO          │
├──────────────┼─────────────┼─────────────────────────────┼───────────────┤
│ 📦 app       │ running     │ https://app.local           │ PHP 8.4       │
├──────────────┼─────────────┼─────────────────────────────┼───────────────┤
│ 🐘 db        │ stopped     │ localhost:5432              │ PostgreSQL 16 │
└──────────────┴─────────────┴─────────────────────────────┴───────────────┘
```

## Implementation

### Rendering Strategy

1. Use Symfony Table to render the data section (headers + rows)
2. Capture output to calculate total table width
3. Render header lines manually with full-width span
4. Combine: header section + modified data section (replace top border with connector)

### Style Configuration

```php
$style = (new TableStyle())
    ->setHorizontalBorderChars('─')
    ->setVerticalBorderChars('│', '│')
    ->setCrossingChars('┼', '┌', '┬', '┐', '┤', '┘', '┴', '└', '├')
    ->setCellHeaderFormat('<fg=default;options=bold>%s</>')
    ->setCellRowFormat('<fg=default>%s</>');
```

Borders colored with `<fg=gray>` for consistency.

### Row Separators

Use `Table::separator()` static method returning a marker, then convert to `TableSeparator` during rendering.

### Multi-line Cells

Convert array cells to newline-separated strings before passing to Symfony Table:
```php
['line1', 'line2'] → "line1\nline2"
```

## Integration

Update `InspectCommand` to use the new Table widget for DDEV-like output.
