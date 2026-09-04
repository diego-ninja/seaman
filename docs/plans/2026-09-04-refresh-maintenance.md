# Seaman Maintenance Refresh Implementation Plan

> **For agents:** REQUIRED SUB-SKILL: Use viterbit:executing-plans to implement this plan task-by-task.

**Goal:** Eliminate known security advisories, fix the audited data-loss and lifecycle bugs, and restore a reproducible quality baseline without adopting major dependency versions.

**Architecture:** Preserve the existing CLI and value-object design. Make configuration round-tripping lossless, make destructive operations fail closed, centralize Docker Compose file/command resolution, and introduce only the dependency injection seams required for deterministic tests.

**Tech Stack:** PHP 8.4+, Symfony Console/Process/Yaml 7.4, Symfony EventDispatcher 8.1, Twig 3, Pest 4, PHPStan 2, GitHub Actions.

---

### Task 1: Patch dependencies within current constraints

**Files:**
- Modify: `composer.lock`

1. Run the targeted Composer update for Twig, PHPUnit, Symfony Process/Yaml and their transitive dependencies.
2. Run `composer audit --locked` and require zero advisories.
3. Run PHPStan on the configured production scope and the non-Docker tests.
4. Commit as `chore: patch vulnerable dependencies`.

### Task 2: Make configuration round-trips lossless

**Files:**
- Modify: `src/ValueObject/ServiceConfig.php`
- Modify: `src/Service/ConfigParser/ServiceConfigParser.php`
- Modify: `src/Service/ConfigManager.php`
- Modify: `src/Command/ServiceAddCommand.php`
- Modify: `src/Command/ServiceRemoveCommand.php`
- Modify: `src/Command/ConfigureCommand.php`
- Test: `tests/Unit/Service/ConfigManagerTest.php`
- Test: command tests under `tests/Integration/Command/`

1. Add a failing round-trip test containing project type, disabled proxy, DNS provider, custom services, plugins and nested service `config`.
2. Add failing add/remove tests proving unrelated fields survive.
3. Carry nested service configuration in `ServiceConfig` and parse/serialize it.
4. Replace partial `new Configuration(...)` copies with `Configuration::with()`.
5. Preserve all top-level fields in `ConfigManager::merge()`.
6. Regenerate Compose/environment before restarting after `service:configure`.
7. Run focused tests, PHPStan and CS Fixer.
8. Commit as `fix: preserve complete service configuration`.

### Task 3: Make cleanup fail closed and symlink-safe

**Files:**
- Modify: `src/Command/CleanCommand.php`
- Test: `tests/Integration/Command/CleanCommandTest.php`

1. Add failing tests with file and directory symlinks pointing outside the project.
2. Add failing tests for Docker/DNS cleanup failure and backup/env-only cleanup.
3. Delete link pathnames without dereferencing them.
4. Stop before local deletion when prerequisite cleanup fails.
5. Include backups and managed `.env` sections in the nothing-to-clean predicate.
6. Run focused tests, PHPStan and CS Fixer.
7. Commit as `fix: make cleanup safe and fail closed`.

### Task 4: Normalize Docker Compose execution

**Files:**
- Modify: `src/Service/DockerManager.php`
- Modify: `src/Service/Detector/ModeDetector.php`
- Test: `tests/Unit/Service/DockerManagerTest.php`
- Test: `tests/Unit/Service/ModeDetectorTest.php`

1. Add failing tests for both Compose filename extensions and non-log timeouts.
2. Resolve `.yml` and `.yaml` consistently.
3. Use the Compose V2 command form (`docker compose`) through one command prefix.
4. Treat general timeouts as failure and tolerate only follow-log termination.
5. Parse JSON-lines status without assuming a trailing newline.
6. Run focused tests, PHPStan and CS Fixer.
7. Commit as `fix: normalize docker compose execution`.

### Task 5: Propagate the effective initialization root

**Files:**
- Modify: `src/Command/InitCommand.php`
- Modify: `src/Service/InitializationWizard.php`
- Test: `tests/Integration/Command/InitCommandTest.php`

1. Add a failing test for bootstrapping from a non-empty directory.
2. Decide the target path before collecting configuration choices.
3. Return and use the effective project root for initialization, devcontainer generation, DNS and lifecycle events.
4. Remove the hard-coded ambiguous `symfony-app` fallback.
5. Run focused tests, PHPStan and CS Fixer.
6. Commit as `fix: propagate initialized project root`.

### Task 6: Preserve Composer package identity for plugins

**Files:**
- Modify: `src/Plugin/LoadedPlugin.php`
- Modify: `src/Plugin/PluginRegistry.php`
- Modify: `src/Plugin/Loader/ComposerPluginLoader.php`
- Modify: `src/Command/Plugin/PluginInstallCommand.php`
- Modify: `src/Command/Plugin/PluginRemoveCommand.php`
- Test: plugin loader/command tests under `tests/Unit/Plugin/`

1. Add a failing test where plugin name differs from `vendor/package`.
2. Carry the optional package name from discovery into `LoadedPlugin`.
3. Use package identity for Composer install/remove filtering and logical name elsewhere.
4. Make Packagist validation failure explicit instead of silently accepting an unverified package.
5. Run focused tests, PHPStan and CS Fixer.
6. Commit as `fix: retain composer plugin identity`.

### Task 7: Restore deterministic quality gates

**Files:**
- Modify: `composer.json`
- Modify: `phpstan.neon`
- Modify: `phpunit.xml`
- Modify: `tests/Pest.php`
- Modify: `tests/Integration/ApplicationTest.php`
- Modify: `.github/workflows/code-style.yml`
- Modify/Create: GitHub Actions quality workflow
- Modify: `.github/workflows/security.yml`

1. Fix the stale version assertions and remove the nonexistent coverage path.
2. Separate Docker-required tests from the default deterministic suite.
3. Align the Composer PHPStan script with the validated configuration.
4. Set coverage mode explicitly in the coverage script/workflow.
5. Use `composer install` in CI and add PHPStan, tests, PHAR smoke and scheduled audit gates.
6. Run the default quality pipeline locally.
7. Commit as `ci: restore deterministic quality gates`.

### Task 8: Remove unused direct dependencies and verify

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Optionally modify: `tests/Unit/Service/DnsProviderDetectorTest.php`

1. Remove `ramsey/collection` and the redundant direct `pest-plugin-arch` requirement.
2. Keep Mockery unless replacing its only use is smaller and clearer.
3. Run `composer validate --strict`, `composer audit --locked`, PHPStan, CS Fixer, deterministic tests and PHAR smoke.
4. Document any Docker-only checks not executable in the current environment.
5. Commit as `chore: remove unused dependencies`.
