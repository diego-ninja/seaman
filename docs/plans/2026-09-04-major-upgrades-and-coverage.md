# Seaman Major Upgrades and Coverage Plan

> **For agents:** REQUIRED SUB-SKILL: Use viterbit-ai-tools:executing-plans to implement this plan task-by-task.

**Goal:** Complete the maintenance refresh by adopting the remaining supported major dependencies, modernizing CI, increasing deterministic coverage, and moving Docker integration checks to an environment with a daemon.

**Architecture:** Raise the runtime baseline to PHP 8.5 so Symfony 8, Pest 5, and innmind/signals 5 can move together. Preserve the CLI's public behavior, adapt only the integration points broken by upstream APIs, and keep daemon-dependent tests separate from the fast local suite.

**Tech Stack:** PHP 8.5+, Symfony 8.1, Pest 5/PHPUnit 13, innmind/signals 5, PHPStan 2, GitHub Actions, Docker Compose V2.

---

### Task 1: Upgrade the remaining Composer majors

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Modify: `src/Signal/SignalHandler.php`
- Modify as required by upstream compatibility: `src/Application.php`, `phpunit.xml`, tests

1. Raise the PHP requirement to `^8.5` and update Symfony Console/Process/Yaml, Pest, and innmind/signals constraints.
2. Run a full dependency update with transitive dependencies.
3. Add a focused signal-handler compatibility test before adapting to the new fallible listener API.
4. Fix only concrete Symfony 8, Pest 5, PHPUnit 13, or Signals 5 regressions exposed by static analysis and tests.
5. Run Composer validation/audit, PHPStan, CS Fixer, and the deterministic tests.
6. Commit as `chore: upgrade core dependencies`.

### Task 2: Modernize GitHub Actions and add Docker integration CI

**Files:**
- Modify: `.github/workflows/quality.yml`
- Modify: `.github/workflows/code-style.yml`
- Modify: `.github/workflows/security.yml`
- Modify: `.github/workflows/release.yml`
- Create or modify: `.github/workflows/docker.yml`

1. Update workflows to PHP 8.5.
2. Upgrade `actions/checkout` to v6, `actions/cache` to v5, and `ramsey/composer-install` to v4.
3. Add a Docker integration job on an Ubuntu runner with explicit daemon and Compose preflight checks.
4. Validate workflow YAML and preserve least-privilege permissions.
5. Commit as `ci: modernize actions and test docker integration`.

### Task 3: Cover deterministic database-service selection

**Files:**
- Test: database command tests or a dedicated test fixture under `tests/Unit/Command/`
- Modify only if a tested defect is exposed: `src/Command/Concern/SelectsDatabaseService.php`
- Modify: `composer.json`

1. Add tests for explicit service selection, the single-service shortcut, interactive selection, invalid selection, and configurations without databases.
2. Keep the tests daemon-free by exercising the selection boundary with fakes.
3. Raise the coverage floor to the newly proven stable percentage.
4. Run the focused tests and full deterministic coverage suite.
5. Commit as `test: cover database service selection`.

### Task 4: Final verification and review

**Files:**
- Modify only for confirmed defects found during review.

1. Run Composer validation and security audit.
2. Run PHPStan, CS Fixer, deterministic tests with coverage, PHAR build, and PHAR smoke test.
3. Request a final code review of the complete branch diff and resolve actionable findings.
4. Record that the Docker suite is delegated to CI when no local daemon is available.
5. Commit any review fixes with a scoped conventional commit.
