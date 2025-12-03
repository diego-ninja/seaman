# Phase 6: Testing & Documentation - Implementation Plan

**Phase**: 6 of 6 (Final)
**Goal**: Ensure 95%+ coverage, verify all features, and document everything
**Dependencies**: Phases 1-5 completed
**Estimated Tasks**: 8 tasks
**Testing Strategy**: Coverage verification, manual testing, documentation

## Overview

Final phase ensures code quality, comprehensive testing, and complete documentation. Verifies all features work end-to-end and provides migration guide for existing users.

## Prerequisites

- All phases 1-5 completed and committed
- All unit tests passing
- PHPStan level 10 clean
- Working in `.worktrees/dual-mode-traefik-import` branch

## Implementation Tasks

### Task 1: Verify Unit Test Coverage (95%+)

**Goal**: Ensure all business logic has comprehensive unit tests

**Run Coverage Report**:
```bash
vendor/bin/pest --coverage --min=95
```

**Expected Coverage by Component**:

**Phase 1 (Foundation)**:
- OperatingMode: 100%
- ModeDetector: 100%
- PortChecker: 100%
- ConfigurationValidator: 100%
- ProjectDetector: 100%
- All Exceptions: 100%

**Phase 2 (Traefik)**:
- ProxyConfig: 100%
- CertificateManager: 95%+
- CertificateResult: 100%
- TraefikLabelGenerator: 100%
- TraefikService: 100%
- ServiceExposureType: 100%

**Phase 3 (DNS)**:
- DnsConfigurationResult: 100%
- DnsConfigurationHelper: 95%+

**Phase 4 (Import)**:
- DetectedService: 100%
- CustomServiceCollection: 100%
- ServiceDetector: 100%
- ComposeImporter: 100%

**Phase 5 (Unmanaged Mode)**:
- No new units to test (logic in existing classes)

**If coverage < 95%**:
1. Identify uncovered lines: `vendor/bin/pest --coverage`
2. Write additional tests for edge cases
3. Re-run until 95%+ achieved

---

### Task 2: Manual End-to-End Testing

**Goal**: Verify all features work in real scenarios

**Test Scenarios**:

#### Scenario 1: Fresh Symfony Project Init
```bash
# 1. Create new Symfony project
composer create-project symfony/skeleton test-seaman-fresh
cd test-seaman-fresh

# 2. Initialize seaman
seaman init

# Verify:
# - Wizard runs
# - Selects services (PostgreSQL, Redis, Mailpit)
# - Generates certificates (mkcert or self-signed)
# - Creates .seaman/seaman.yaml
# - Creates docker-compose.yml with Traefik
# - Offers DNS configuration
# - All files created correctly

# 3. Start services
seaman start

# Verify:
# - All containers start
# - No port conflicts
# - Traefik dashboard accessible (if DNS configured)

# 4. Test commands
seaman status  # Shows running services
seaman logs app  # Shows logs
seaman shell  # Opens shell in app container
seaman xdebug:on  # Enables Xdebug

# 5. Add service
seaman service:add mysql

# Verify:
# - mysql added to seaman.yaml
# - docker-compose.yml regenerated
# - Can restart services successfully

# 6. Cleanup
seaman destroy

# Verify:
# - Containers stopped and removed
# - Volumes removed
# - Offers to remove DNS config
```

#### Scenario 2: Import Existing docker-compose.yaml
```bash
# 1. Create test directory with docker-compose.yml
mkdir test-seaman-import
cd test-seaman-import

# 2. Create docker-compose.yml
cat > docker-compose.yml <<EOF
version: '3.8'
services:
  postgres:
    image: postgres:16
    ports:
      - "5432:5432"
    environment:
      POSTGRES_PASSWORD: secret

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"

  my-custom-app:
    image: mycompany/app:latest
    ports:
      - "8080:80"
    environment:
      API_KEY: secret123
EOF

# 3. Initialize with import
seaman init

# Verify:
# - Detects docker-compose.yml
# - Offers to import
# - Shows detected services (postgres, redis)
# - Shows custom service (my-custom-app)
# - Asks for confirmation
# - Backs up original as docker-compose.yml.backup-*

# 4. Check seaman.yaml
cat .seaman/seaman.yaml

# Verify:
# - postgres and redis in services section
# - my-custom-app in custom_services section

# 5. Check generated docker-compose.yml
cat docker-compose.yml

# Verify:
# - Includes Traefik
# - Includes postgres with Traefik labels (disabled)
# - Includes redis with Traefik labels (disabled)
# - Includes my-custom-app (preserved exactly)
# - All services in seaman network

# 6. Start and verify
seaman start
seaman status
```

#### Scenario 3: Unmanaged Mode (No Init)
```bash
# 1. Create directory with only docker-compose.yml
mkdir test-seaman-unmanaged
cd test-seaman-unmanaged

# 2. Create docker-compose.yml (same as Scenario 2)

# 3. Use seaman commands WITHOUT init
seaman start  # Should work
seaman status  # Should show services
seaman logs postgres  # Should show logs
seaman stop  # Should work

# 4. Try managed-only command
seaman service:add mysql

# Verify:
# - Shows upgrade message
# - Explains benefits of seaman init
# - Mentions seaman init --import

# 5. Try xdebug
seaman xdebug:on

# Verify:
# - Shows upgrade message
```

#### Scenario 4: DNS Configuration
```bash
# In a seaman-initialized project

# Test automatic (if dnsmasq available)
seaman proxy:configure-dns

# Verify:
# - Detects dnsmasq
# - Shows configuration path
# - Offers to write config
# - Restarts dnsmasq
# - Domains resolve correctly

# Test manual (force manual mode)
# Show instructions and verify they're clear

# Test cleanup
seaman destroy

# Verify:
# - Offers to remove DNS config
# - Removes config if confirmed
# - Restarts dnsmasq
```

**Document Results**: Create `TESTING.md` with scenarios and results

---

### Task 3: Update README.md

**File**: `README.md`

**Add/Update Sections**:

1. **Features Section** - Add new features:
```markdown
## Features

- 🚢 Intelligent Symfony project detection and initialization
- 🐳 Docker Compose generation with 15+ pre-configured services
- 🔀 **Traefik reverse proxy with automatic HTTPS**
- 📦 **Dual operating mode: managed or unmanaged**
- 📥 **Import existing docker-compose.yaml files**
- 🌐 Smart DNS configuration (dnsmasq, systemd-resolved, or manual)
- 🐞 Xdebug configuration and control
- 🎨 DevContainer support for VS Code
- 📊 Database backup and restore tools
- ⚡ Interactive CLI with wizards
- 🔒 Type-safe PHP 8.4 implementation
- ✅ 95%+ test coverage
```

2. **Quick Start** - Update with new flow:
```markdown
## Quick Start

### New Symfony Project

```bash
composer create-project symfony/skeleton my-project
cd my-project
seaman init
seaman start
```

Your app is now running at `https://app.my-project.local` (after DNS setup)!

### Existing Docker Compose Project

```bash
cd my-existing-project
seaman init --import  # Imports your docker-compose.yaml
```

### Just Try It (No Commitment)

```bash
cd my-project-with-docker-compose
seaman start  # Works immediately, no initialization required!
```

3. **New Section**: Operating Modes
```markdown
## Operating Modes

Seaman works in two modes:

### Managed Mode (Full Features)
When you run `seaman init`, you get:
- ✅ Traefik reverse proxy with HTTPS
- ✅ Service management (add/remove services)
- ✅ Xdebug control
- ✅ DevContainer generation
- ✅ Advanced database tools
- ✅ Automatic service routing

### Unmanaged Mode (Basic Passthrough)
Use seaman with any docker-compose.yaml:
- ✅ Start/stop/restart services
- ✅ View status and logs
- ✅ Access containers
- ✅ Database operations

Upgrade to managed mode anytime with `seaman init --import`!
```

4. **New Section**: Import Existing Projects
```markdown
## Import Existing docker-compose.yaml

Seaman can import your existing Docker Compose configuration:

```bash
seaman init

# Seaman will:
# 1. Detect your docker-compose.yaml
# 2. Recognize standard services (postgres, redis, mysql, etc.)
# 3. Preserve custom services
# 4. Add Traefik reverse proxy
# 5. Configure HTTPS certificates
# 6. Set up DNS (optional)
```

Recognized services become fully managed. Unknown services are preserved exactly as-is in `custom_services` section.
```

5. **HTTPS & DNS Section**:
```markdown
## HTTPS & DNS Configuration

Seaman automatically configures HTTPS with Traefik:

### Certificate Generation
- **mkcert** (if installed): Trusted local certificates, no browser warnings
- **OpenSSL** (fallback): Self-signed certificates (browser shows warnings)

### DNS Setup
Access services via clean URLs instead of ports:
- `https://app.myproject.local` instead of `http://localhost:8000`
- `https://mailpit.myproject.local` instead of `http://localhost:8025`
- `https://traefik.myproject.local` for Traefik dashboard

**Automatic Setup** (if available):
```bash
seaman init  # Offers DNS configuration
# or
seaman proxy:configure-dns
```

Supports:
- dnsmasq (Linux/macOS)
- systemd-resolved (Linux)
- Manual /etc/hosts (all platforms)
```

---

### Task 4: Create Migration Guide

**File**: `docs/MIGRATION.md`

**Content**:
```markdown
# Migration Guide: Upgrading to Seaman 2.0

This guide helps existing Seaman users upgrade to version 2.0 with Traefik, import support, and dual-mode operation.

## Breaking Changes

**None!** Seaman 2.0 is fully backward compatible.

## What's New

1. **Traefik Reverse Proxy**: Automatic HTTPS and service routing
2. **Import Support**: Import existing docker-compose.yaml files
3. **Dual Mode**: Basic commands work without initialization
4. **DNS Management**: Automatic or manual DNS configuration

## Automatic Upgrade

When you run any seaman command in an existing project:

```bash
seaman start
```

Seaman detects it's an older version and offers to upgrade:

```
⚠️  Seaman configuration needs upgrade to version 2.0

New features:
  • Traefik reverse proxy with HTTPS
  • Automatic service routing
  • DNS configuration support

Upgrade now? [Y/n]
```

Choosing "Yes" adds:
- Traefik service to seaman.yaml
- Proxy configuration section
- Regenerates docker-compose.yml with Traefik
- Generates SSL certificates
- Offers DNS configuration

**Your existing services are preserved unchanged.**

## Manual Upgrade

If you prefer manual control:

```bash
seaman init --upgrade
```

This runs the upgrade wizard:
1. Reviews current configuration
2. Adds Traefik service
3. Configures SSL certificates
4. Sets up DNS (optional)
5. Updates docker-compose.yml

## Rollback (If Needed)

If something goes wrong:

```bash
# Seaman backs up your old config
cp .seaman/seaman.yaml.backup .seaman/seaman.yaml
cp docker-compose.yml.backup docker-compose.yml

# Restart with old config
seaman restart
```

## What Doesn't Change

- ✅ All existing services work exactly as before
- ✅ All commands work the same way
- ✅ No changes to volumes or data
- ✅ No changes to environment variables
- ✅ No changes to your PHP/Symfony code

## New Features You Can Use

### Access Services by Domain

Before:
```
http://localhost:8000       # Your app
http://localhost:8025       # Mailpit
http://localhost:15672      # RabbitMQ
```

After (with DNS configured):
```
https://app.myproject.local         # Your app
https://mailpit.myproject.local     # Mailpit
https://rabbitmq.myproject.local    # RabbitMQ
https://traefik.myproject.local     # Traefik dashboard
```

### Import Other Projects

Now you can use seaman with any Docker Compose project:

```bash
cd ~/my-other-project
seaman init --import  # Imports existing docker-compose.yaml
```

### Work Without Init

Try seaman without commitment:

```bash
cd ~/any-docker-project
seaman start  # Just works!
```

## Support

If you encounter issues:
1. Check `seaman.yaml.backup` and `docker-compose.yml.backup`
2. Open an issue: https://github.com/ninja/seaman/issues
3. Include your seaman.yaml (remove secrets!)

## FAQ

**Q: Do I have to upgrade?**
A: No. Your current setup continues working. Upgrade when ready.

**Q: Will my data be affected?**
A: No. Volumes and databases remain unchanged.

**Q: Can I keep using ports instead of domains?**
A: Yes. Direct ports still work:
- PostgreSQL: localhost:5432
- Redis: localhost:6379
- Mailpit SMTP: localhost:1025

**Q: What if mkcert isn't installed?**
A: Seaman uses self-signed certificates. Works fine, just browser warnings.

**Q: Can I customize Traefik configuration?**
A: Yes. Edit `.seaman/traefik/traefik.yml` and restart.

**Q: How do I disable Traefik?**
A: Traefik is required in 2.0, but you can access services directly via ports.
```

---

### Task 5: Update Command Help Text

**Goal**: Ensure all command help is clear and accurate

**Review and update help text for**:

1. **InitCommand**:
```php
$this->setDescription('Initialize seaman in your Symfony project')
     ->setHelp(
         'Sets up Docker development environment with Traefik, HTTPS, and services.' . "\n\n" .
         'For new projects: Creates complete configuration' . "\n" .
         'For existing docker-compose.yaml: Offers to import' . "\n\n" .
         'Examples:' . "\n" .
         '  seaman init                    # Interactive wizard' . "\n" .
         '  seaman init --import           # Force import mode' . "\n" .
         '  seaman init --fresh            # Force fresh configuration'
     );
```

2. **StartCommand**:
```php
$this->setDescription('Start Docker services')
     ->setHelp(
         'Starts all or specific Docker services.' . "\n\n" .
         'Works with any docker-compose.yaml file.' . "\n" .
         'Run "seaman init" for full features (Traefik, HTTPS, etc.).' . "\n\n" .
         'Examples:' . "\n" .
         '  seaman start                   # Start all services' . "\n" .
         '  seaman start postgresql        # Start specific service'
     );
```

3. **Service commands**:
```php
// service:add
$this->setHelp(
    'Add services to seaman configuration.' . "\n\n" .
    'Requires seaman initialization. Run "seaman init" first.' . "\n\n" .
    'Available services: postgresql, mysql, redis, rabbitmq, and more.'
);
```

4. **ProxyConfigureDnsCommand**:
```php
$this->setHelp(
    'Configure DNS for Traefik domains (*.yourproject.local).' . "\n\n" .
    'Supports:' . "\n" .
    '  • dnsmasq (automatic)' . "\n" .
    '  • systemd-resolved (automatic)' . "\n" .
    '  • Manual /etc/hosts entries' . "\n\n" .
    'Run after seaman init or when changing domain prefix.'
);
```

**Verification**:
```bash
seaman list
seaman init --help
seaman start --help
seaman service:add --help
seaman proxy:configure-dns --help
```

---

### Task 6: Create TESTING.md

**File**: `TESTING.md`

**Content**: Document manual test scenarios and expected results

```markdown
# Testing Guide

Seaman uses unit tests for business logic (95%+ coverage) and manual testing for command integration and user experience.

## Unit Tests

Run all unit tests:
```bash
vendor/bin/pest
```

With coverage:
```bash
vendor/bin/pest --coverage --min=95
```

## Manual Test Scenarios

### Scenario 1: Fresh Project Initialization

**Setup**:
```bash
composer create-project symfony/skeleton test-fresh
cd test-fresh
```

**Test**:
```bash
seaman init
```

**Expected**:
1. ✅ Wizard starts
2. ✅ Detects Symfony project
3. ✅ Shows service selection
4. ✅ Generates certificates (mkcert or self-signed message)
5. ✅ Creates `.seaman/seaman.yaml`
6. ✅ Creates `docker-compose.yml` with Traefik
7. ✅ Offers DNS configuration
8. ✅ Success message

**Verify Files**:
- `.seaman/seaman.yaml` exists
- `.seaman/certs/cert.pem` and `key.pem` exist
- `.seaman/traefik/traefik.yml` exists
- `docker-compose.yml` includes Traefik service

### Scenario 2: Import Existing Compose File

**Setup**:
```bash
mkdir test-import
cd test-import
cat > docker-compose.yml <<EOF
version: '3.8'
services:
  postgres:
    image: postgres:16
    ports: ["5432:5432"]
  custom:
    image: myapp:latest
EOF
```

**Test**:
```bash
seaman init
```

**Expected**:
1. ✅ Detects docker-compose.yaml
2. ✅ Offers [I]mport or [C]reate
3. ✅ Choose Import
4. ✅ Shows detected services table
5. ✅ Shows custom services warning
6. ✅ Asks confirmation
7. ✅ Backs up original
8. ✅ Creates seaman.yaml with custom_services section

**Verify**:
- `.seaman/seaman.yaml` has postgres in services
- `.seaman/seaman.yaml` has custom in custom_services
- `docker-compose.yml.backup-*` exists
- New `docker-compose.yml` has Traefik + postgres + custom

### Scenario 3: Unmanaged Mode Commands

**Setup**:
```bash
mkdir test-unmanaged
cd test-unmanaged
cat > docker-compose.yml <<EOF
version: '3.8'
services:
  nginx:
    image: nginx:alpine
    ports: ["8080:80"]
EOF
```

**Test Basic Commands**:
```bash
seaman start     # ✅ Should work
seaman status    # ✅ Should show nginx
seaman logs      # ✅ Should show logs
seaman stop      # ✅ Should work
```

**Test Managed-Only Commands**:
```bash
seaman service:add redis     # ❌ Should show upgrade message
seaman xdebug:on             # ❌ Should show upgrade message
```

**Expected Upgrade Message**:
```
⚠️  This command requires seaman initialization.

Run 'seaman init' to unlock full features:
  • Service management (add/remove services)
  • Xdebug control
  • DevContainer generation
  • Advanced database tools
  • Traefik reverse proxy with HTTPS
  • Automatic service routing

Or use 'seaman init --import' to import your existing docker-compose.yaml
```

### Scenario 4: DNS Configuration

**Setup**: Initialized seaman project

**Test Automatic (if dnsmasq available)**:
```bash
seaman proxy:configure-dns
```

**Expected**:
1. ✅ Detects dnsmasq
2. ✅ Shows config path
3. ✅ Shows config content
4. ✅ Asks for sudo confirmation
5. ✅ Writes config
6. ✅ Restarts dnsmasq
7. ✅ Success message

**Verify**:
```bash
# Config file exists
ls /etc/dnsmasq.d/seaman-*.conf

# DNS resolves
ping app.myproject.local
```

**Test Manual**:
- Choose manual option
- ✅ Shows clear /etc/hosts instructions
- ✅ Lists all service domains
- ✅ Shows platform-specific editor commands

### Scenario 5: Service Management

**Setup**: Initialized seaman project

**Test Add**:
```bash
seaman service:add mysql
```

**Expected**:
1. ✅ Adds mysql to seaman.yaml
2. ✅ Regenerates docker-compose.yml
3. ✅ Shows success message

**Verify**:
```bash
cat .seaman/seaman.yaml  # mysql in services
cat docker-compose.yml   # mysql service present
```

**Test Remove**:
```bash
seaman service:remove mysql
```

**Expected**:
1. ✅ Removes from seaman.yaml
2. ✅ Regenerates docker-compose.yml
3. ✅ Success message

### Scenario 6: Full Lifecycle

**Test**:
```bash
seaman init
seaman start
seaman status
seaman logs app
seaman shell
seaman xdebug:on
seaman service:add redis
seaman restart
seaman destroy
```

**Expected**: All commands succeed, no errors

## Test Checklist

Before release, verify:

- [ ] Fresh init works
- [ ] Import works
- [ ] Unmanaged mode works
- [ ] DNS configuration works
- [ ] Certificate generation works (both mkcert and self-signed)
- [ ] Service add/remove works
- [ ] All basic commands work (start/stop/status/logs)
- [ ] All managed commands show upgrade message in unmanaged mode
- [ ] Help text is clear and accurate
- [ ] No PHP errors or warnings
- [ ] PHPStan level 10 passes
- [ ] 95%+ test coverage
- [ ] README is up to date
- [ ] Migration guide is clear

## Reporting Issues

When reporting bugs, include:
1. Seaman version (`seaman --version`)
2. Operating mode (managed/unmanaged)
3. Contents of `.seaman/seaman.yaml` (remove secrets!)
4. Error message or unexpected behavior
5. Steps to reproduce
```

---

### Task 7: Run Final Quality Checks

**Execute All Quality Tools**:

```bash
# 1. Run all unit tests with coverage
vendor/bin/pest --coverage --min=95

# 2. Run PHPStan (level 10)
vendor/bin/phpstan analyse

# 3. Run PHP CS Fixer
vendor/bin/php-cs-fixer fix --dry-run --diff

# 4. Check for ABOUTME comments
grep -r "class\|interface\|trait" src/ | grep -v "ABOUTME" | wc -l
# Should be 0 (all files have ABOUTME)

# 5. Verify composer.json is valid
composer validate

# 6. Check for TODO comments (should be addressed)
grep -r "TODO" src/ || echo "No TODOs found"

# 7. Run build (if PHAR)
composer build
./build/seaman.phar --version
```

**Expected Results**:
- ✅ All tests pass
- ✅ Coverage ≥ 95%
- ✅ PHPStan: 0 errors
- ✅ PHP CS Fixer: no changes needed
- ✅ All files have ABOUTME comments
- ✅ Composer.json valid
- ✅ PHAR builds successfully

---

### Task 8: Final Commit and Merge Preparation

**Review All Changes**:
```bash
git status
git log --oneline
git diff main..feature/dual-mode-traefik-import --stat
```

**Create Final Summary Commit** (if needed):
```bash
git add -A
git commit -m "feat: complete dual-mode, Traefik, and import implementation

All phases completed:
- Phase 1: Foundation (operating modes, validation, port checking)
- Phase 2: Traefik integration with HTTPS
- Phase 3: DNS management
- Phase 4: docker-compose import mechanism
- Phase 5: Unmanaged mode support
- Phase 6: Testing and documentation

Features:
- Dual operating mode (managed/unmanaged)
- Traefik reverse proxy with automatic HTTPS
- Import existing docker-compose.yaml files
- Smart DNS configuration
- 95%+ unit test coverage
- PHPStan level 10 compliant
- Comprehensive documentation

Breaking changes: None (fully backward compatible)

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com)"
```

**Tag Release** (if ready):
```bash
git tag -a v2.0.0 -m "Release 2.0.0: Dual Mode, Traefik, Import Support"
```

**Prepare for Merge**:
```bash
# Ensure main is up to date
git checkout main
git pull origin main

# Switch back to feature branch
git checkout feature/dual-mode-traefik-import

# Rebase on main (if needed)
git rebase main

# Push feature branch
git push origin feature/dual-mode-traefik-import
```

---

## Final Phase 6 Verification

Complete final checklist:

```
✅ Unit test coverage ≥ 95%
✅ All manual test scenarios pass
✅ README.md updated with new features
✅ MIGRATION.md created
✅ TESTING.md created with scenarios
✅ All command help text updated
✅ PHPStan level 10: 0 errors
✅ PHP CS Fixer: clean
✅ All ABOUTME comments present
✅ Composer validate: pass
✅ PHAR builds successfully
✅ All phases committed
✅ Ready for merge to main
```

## Success Criteria

- ✅ All 8 tasks completed
- ✅ 95%+ unit test coverage verified
- ✅ All manual scenarios tested and documented
- ✅ Documentation complete and accurate
- ✅ Migration guide clear and helpful
- ✅ Code quality tools all pass
- ✅ Feature branch ready for merge
- ✅ Release tagged (if applicable)

## Post-Phase Actions

After Phase 6:

1. **Create Pull Request**:
   - From: `feature/dual-mode-traefik-import`
   - To: `main`
   - Include: Summary of all changes, link to design doc, testing notes

2. **Code Review**:
   - Self-review using GitHub PR interface
   - Check for any missed issues
   - Verify all tests pass in CI

3. **Merge**:
   - Squash or merge based on project preference
   - Delete feature branch after merge
   - Tag release on main

4. **Release**:
   - Create GitHub release
   - Include migration guide
   - Announce new features

## Celebration! 🎉

All 6 phases complete. Seaman 2.0 is ready!
