# Plugin Export Command - Design Document

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add `seaman plugin:export` command to convert local plugins into distributable Composer packages, and unify plugin structure across all plugin types.

**Architecture:** Update `plugin:create` to generate `src/` structure, then implement `plugin:export` to transform local plugins into Packagist-ready packages with namespace transformation and composer.json generation.

**Tech Stack:** PHP 8.4, Symfony Console, Composer package format

---

## Unified Plugin Structure

All plugin types (bundled, local, Composer) will use this structure:

```
my-plugin/
├── composer.json           # Only for Composer plugins
├── src/
│   ├── MyPlugin.php        # Main plugin class
│   ├── Command/            # CLI commands (optional)
│   │   └── MyCommand.php
│   └── Service/            # Helper classes (optional)
│       └── Helper.php
└── templates/              # Twig templates (optional)
    └── service.yaml.twig
```

## Command Interface

```bash
seaman plugin:export [plugin-name] [--output=DIR] [--vendor=NAME]
```

### Arguments & Options

| Argument/Option | Description | Default |
|-----------------|-------------|---------|
| `plugin-name` | Name of local plugin to export | Interactive selection |
| `--output` | Output directory | `./exports/<plugin-name>/` |
| `--vendor` | Vendor name for Composer | Interactive (from git config) |

## Execution Flow

1. **Selection**: If no argument, list local plugins and prompt for selection
2. **Validation**:
   - Plugin exists in `.seaman/plugins/<name>/`
   - Has `src/` with at least one PHP file
   - Main file has `#[AsSeamanPlugin]` attribute
3. **Interactive data**:
   - Vendor name (default: inferred from git config or "your-vendor")
   - Description (default: from `#[AsSeamanPlugin]` attribute)
4. **Generation**:
   - Create output directory
   - Copy `src/` transforming namespaces
   - Copy `templates/` unchanged
   - Generate `composer.json`
5. **Result**: Show path and publishing instructions

## Namespace Transformation

### Before (local plugin)
```php
namespace Seaman\LocalPlugins\MyPlugin;
use Seaman\LocalPlugins\MyPlugin\Command\MyCommand;
```

### After (exported)
```php
namespace Diego\MyPlugin;
use Diego\MyPlugin\Command\MyCommand;
```

Transformation applies to:
- `namespace` declarations
- `use` statements
- Fully-qualified class references

## Generated composer.json

```json
{
    "name": "diego/my-plugin",
    "description": "Description from #[AsSeamanPlugin]",
    "type": "seaman-plugin",
    "license": "MIT",
    "require": {
        "php": "^8.4"
    },
    "require-dev": {
        "seaman/seaman": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "Diego\\MyPlugin\\": "src/"
        }
    },
    "extra": {
        "seaman": {
            "plugin-class": "Diego\\MyPlugin\\MyPluginPlugin"
        }
    }
}
```

## Implementation Tasks

### Task 1: Update PluginCreateCommand

**Files:**
- Modify: `src/Command/Plugin/PluginCreateCommand.php`
- Test: `tests/Unit/Command/Plugin/PluginCreateCommandTest.php`

**Changes:**
- Create `src/` subdirectory instead of placing PHP file in root
- Update path: `.seaman/plugins/<name>/src/<Name>Plugin.php`
- Keep `templates/` at plugin root level

### Task 2: Create PluginExportCommand

**Files:**
- Create: `src/Command/Plugin/PluginExportCommand.php`
- Create: `tests/Unit/Command/Plugin/PluginExportCommandTest.php`

**Responsibilities:**
- List and select local plugins
- Validate plugin structure
- Prompt for vendor name
- Delegate to export service

### Task 3: Create PluginExporter Service

**Files:**
- Create: `src/Plugin/Export/PluginExporter.php`
- Create: `src/Plugin/Export/NamespaceTransformer.php`
- Create: `tests/Unit/Plugin/Export/PluginExporterTest.php`
- Create: `tests/Unit/Plugin/Export/NamespaceTransformerTest.php`

**PluginExporter responsibilities:**
- Copy directory structure
- Transform namespaces in PHP files
- Generate composer.json
- Extract metadata from plugin attribute

**NamespaceTransformer responsibilities:**
- Parse PHP files
- Replace namespace declarations
- Replace use statements
- Replace fully-qualified references

### Task 4: Update Documentation

**Files:**
- Modify: `docs/plugins.md`
- Modify: `docs/commands.md`

**Changes:**
- Document unified plugin structure
- Document `plugin:export` command
- Update `plugin:create` documentation

## Validation Rules

1. Plugin must exist in `.seaman/plugins/<name>/`
2. Plugin must have `src/` directory
3. At least one PHP file must have `#[AsSeamanPlugin]` attribute
4. Only one main plugin class per plugin
5. Warn if output directory already exists

## Success Output

```
✓ Plugin exported successfully!

  Location: ./exports/my-plugin/

  Next steps:
  1. cd exports/my-plugin
  2. Review and customize composer.json
  3. Initialize git: git init
  4. Publish to Packagist: composer publish

  Install with: composer require diego/my-plugin
```
