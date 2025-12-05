# Mode-Based Command Filtering Design

## Overview

Filter available commands based on the current operating mode (Managed/Unmanaged/Uninitialized) so users only see commands they can actually use.

## Current State

- `ModeDetector` exists and correctly detects the operating mode
- `ModeAwareCommand::supportsMode()` is implemented in each command
- `ModeDetector` is never injected into commands, so mode checking is skipped
- All commands appear in `seaman list` regardless of mode

## Design Decisions

### 1. Command Filtering Strategy

**Approach**: Override `Application::all()` and `Application::find()` to filter commands based on current mode.

**Rationale**: Clean separation of concerns. Commands don't need to know about filtering - they just declare what modes they support.

### 2. Mode Indicator Display

- **In `seaman list`**: Show mode in header: `🔱 Seaman 1.0.0-beta [Managed Mode]`
- **In individual commands**: No indicator (keep banner clean)

### 3. Unavailable Command Message

When user tries to run a filtered command:
```
Command "service:add" is not available in Unmanaged mode.
Run "seaman init" to enable all features.
```

### 4. Interface for Mode Support

Create `ModeAwareInterface` to allow `Application` to query command mode support without exposing protected methods.

## Implementation

### New Interface: `ModeAwareInterface`

```php
namespace Seaman\Contract;

use Seaman\Enum\OperatingMode;

interface ModeAwareInterface
{
    public function supportsMode(OperatingMode $mode): bool;
}
```

### Changes to `ModeAwareCommand`

- Implement `ModeAwareInterface`
- Change `supportsMode()` from `protected` to `public`

### Changes to `Application`

1. Add `ModeDetector` as property, instantiate in constructor
2. Override `all()` to filter commands by mode
3. Override `find()` to throw custom exception for filtered commands
4. Override `getName()` or use custom `renderApplicationTitle()` to show mode

### New Exception: `CommandNotAvailableException`

For when user tries to run a command not available in current mode.

## Files to Create/Modify

| File | Action |
|------|--------|
| `src/Contract/ModeAwareInterface.php` | Create |
| `src/Exception/CommandNotAvailableException.php` | Create |
| `src/Command/ModeAwareCommand.php` | Modify (implement interface, make method public) |
| `src/Application.php` | Modify (add filtering logic) |

## Command Mode Support Matrix

| Command | Managed | Unmanaged | Uninitialized |
|---------|---------|-----------|---------------|
| `seaman:init` | ✓ | ✓ | ✓ |
| `seaman:start/stop/restart` | ✓ | ✓ | ✗ |
| `seaman:status/logs/shell` | ✓ | ✓ | ✗ |
| `seaman:rebuild/destroy` | ✓ | ✓ | ✗ |
| `exec:php/composer/console` | ✓ | ✓ | ✗ |
| `db:dump/restore/shell` | ✓ | ✓ | ✗ |
| `service:list/add/remove` | ✓ | ✗ | ✗ |
| `proxy:enable/disable/configure-dns` | ✓ | ✗ | ✗ |
| `seaman:xdebug` | ✓ | ✗ | ✗ |
| `devcontainer:generate` | ✓ | ✗ | ✗ |

## Testing Strategy

1. Unit tests for `Application::all()` filtering
2. Unit tests for `Application::find()` with unavailable command
3. Integration test: verify `seaman list` output changes based on mode
4. Integration test: verify error message for unavailable command
