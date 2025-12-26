# Plugin Autoloader Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enable Seaman PHAR to load Composer-installed plugins from the project's vendor directory.

**Architecture:** Create an isolated PSR-4 autoloader that registers only plugin packages and their dependencies, avoiding conflicts with the PHAR's internal autoloader.

**Tech Stack:** PHP 8.4, spl_autoload_register, Composer installed.json parsing

---

## Task 1: Create PluginAutoloader Class

**Files:**
- Create: `src/Plugin/Loader/PluginAutoloader.php`
- Test: `tests/Unit/Plugin/Loader/PluginAutoloaderTest.php`

**Step 1: Write the failing test for loadClass**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin\Loader;

use PHPUnit\Framework\TestCase;
use Seaman\Plugin\Loader\PluginAutoloader;

final class PluginAutoloaderTest extends TestCase
{
    public function testLoadClassReturnsFalseWhenNoMappingsRegistered(): void
    {
        $autoloader = new PluginAutoloader();

        $result = $autoloader->loadClass('NonExistent\\SomeClass');

        self::assertFalse($result);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Loader/PluginAutoloaderTest.php`
Expected: FAIL with "Class not found"

**Step 3: Write minimal PluginAutoloader implementation**

```php
<?php

// ABOUTME: Autoloader aislado para plugins de Composer y sus dependencias.
// ABOUTME: Solo carga clases PSR-4 de paquetes relacionados con plugins de Seaman.

declare(strict_types=1);

namespace Seaman\Plugin\Loader;

final class PluginAutoloader
{
    /** @var array<string, list<string>> namespace prefix => base paths */
    private array $prefixPaths = [];

    private bool $registered = false;

    public function loadClass(string $class): bool
    {
        foreach ($this->prefixPaths as $prefix => $paths) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relativeClass = substr($class, strlen($prefix));
            $file = str_replace('\\', '/', $relativeClass) . '.php';

            foreach ($paths as $basePath) {
                $fullPath = $basePath . $file;
                if (file_exists($fullPath)) {
                    require $fullPath;
                    return true;
                }
            }
        }

        return false;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Loader/PluginAutoloaderTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Plugin/Loader/PluginAutoloader.php tests/Unit/Plugin/Loader/PluginAutoloaderTest.php
git commit -m "feat(plugin): add PluginAutoloader skeleton with loadClass method"
```

---

## Task 2: Add PSR-4 Mapping Support

**Files:**
- Modify: `src/Plugin/Loader/PluginAutoloader.php`
- Modify: `tests/Unit/Plugin/Loader/PluginAutoloaderTest.php`

**Step 1: Write test for addPsr4Mappings**

```php
public function testLoadClassResolvesClassFromAddedMappings(): void
{
    $autoloader = new PluginAutoloader();

    // Create temp directory with a test class
    $tempDir = sys_get_temp_dir() . '/plugin-autoloader-test-' . uniqid();
    mkdir($tempDir . '/src', 0777, true);
    file_put_contents(
        $tempDir . '/src/TestPlugin.php',
        '<?php namespace TestVendor\TestPlugin; class TestPlugin {}'
    );

    $package = [
        'name' => 'test-vendor/test-plugin',
        'install-path' => $tempDir,
        'autoload' => [
            'psr-4' => [
                'TestVendor\\TestPlugin\\' => 'src/',
            ],
        ],
    ];

    $autoloader->addPackageMappings($package, '');

    $result = $autoloader->loadClass('TestVendor\\TestPlugin\\TestPlugin');

    self::assertTrue($result);
    self::assertTrue(class_exists('TestVendor\\TestPlugin\\TestPlugin', false));

    // Cleanup
    unlink($tempDir . '/src/TestPlugin.php');
    rmdir($tempDir . '/src');
    rmdir($tempDir);
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Loader/PluginAutoloaderTest.php`
Expected: FAIL with "method addPackageMappings not found"

**Step 3: Implement addPackageMappings**

```php
/**
 * @param array{install-path?: string, autoload?: array{psr-4?: array<string, string|list<string>>}} $package
 */
public function addPackageMappings(array $package, string $vendorDir): void
{
    $psr4 = $package['autoload']['psr-4'] ?? [];
    $installPath = $package['install-path'] ?? '';

    if (empty($psr4) || $installPath === '') {
        return;
    }

    $basePath = $vendorDir !== ''
        ? rtrim($vendorDir, '/') . '/' . $installPath . '/'
        : $installPath . '/';

    foreach ($psr4 as $prefix => $path) {
        $paths = is_array($path) ? $path : [$path];

        foreach ($paths as $p) {
            $fullPath = $basePath . rtrim($p, '/') . '/';
            $this->prefixPaths[$prefix][] = $fullPath;
        }
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Loader/PluginAutoloaderTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add -u
git commit -m "feat(plugin): add PSR-4 mapping support to PluginAutoloader"
```

---

## Task 3: Add Dependency Resolution

**Files:**
- Modify: `src/Plugin/Loader/PluginAutoloader.php`
- Modify: `tests/Unit/Plugin/Loader/PluginAutoloaderTest.php`

**Step 1: Write test for resolveWithDependencies**

```php
public function testResolveWithDependenciesIncludesTransitiveDependencies(): void
{
    $autoloader = new PluginAutoloader();

    $packages = [
        [
            'name' => 'acme/seaman-redis',
            'require' => ['predis/predis' => '^2.0'],
            'autoload' => ['psr-4' => ['Acme\\Redis\\' => 'src/']],
            'install-path' => '../acme/seaman-redis',
        ],
        [
            'name' => 'predis/predis',
            'require' => ['php' => '>=8.1'],
            'autoload' => ['psr-4' => ['Predis\\' => 'src/']],
            'install-path' => '../predis/predis',
        ],
        [
            'name' => 'unrelated/package',
            'autoload' => ['psr-4' => ['Unrelated\\' => 'src/']],
            'install-path' => '../unrelated/package',
        ],
    ];

    $resolved = $autoloader->resolveWithDependencies(
        ['acme/seaman-redis'],
        $packages,
    );

    $resolvedNames = array_column($resolved, 'name');

    self::assertContains('acme/seaman-redis', $resolvedNames);
    self::assertContains('predis/predis', $resolvedNames);
    self::assertNotContains('unrelated/package', $resolvedNames);
}

public function testResolveWithDependenciesIgnoresPlatformDependencies(): void
{
    $autoloader = new PluginAutoloader();

    $packages = [
        [
            'name' => 'acme/seaman-redis',
            'require' => [
                'php' => '>=8.1',
                'ext-json' => '*',
                'lib-pcre' => '*',
            ],
            'autoload' => ['psr-4' => ['Acme\\Redis\\' => 'src/']],
            'install-path' => '../acme/seaman-redis',
        ],
    ];

    $resolved = $autoloader->resolveWithDependencies(
        ['acme/seaman-redis'],
        $packages,
    );

    self::assertCount(1, $resolved);
    self::assertSame('acme/seaman-redis', $resolved[0]['name']);
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Loader/PluginAutoloaderTest.php`
Expected: FAIL with "method resolveWithDependencies not found"

**Step 3: Implement resolveWithDependencies**

```php
/**
 * @param list<string> $pluginNames
 * @param list<array<string, mixed>> $installedPackages
 * @return list<array<string, mixed>>
 */
public function resolveWithDependencies(
    array $pluginNames,
    array $installedPackages,
): array {
    $packagesByName = [];
    foreach ($installedPackages as $package) {
        $packagesByName[$package['name']] = $package;
    }

    $resolved = [];
    $queue = $pluginNames;
    $seen = [];

    while (!empty($queue)) {
        $name = array_shift($queue);

        if (isset($seen[$name])) {
            continue;
        }
        $seen[$name] = true;

        if (!isset($packagesByName[$name])) {
            continue;
        }

        $package = $packagesByName[$name];
        $resolved[] = $package;

        $requires = $package['require'] ?? [];
        foreach (array_keys($requires) as $dep) {
            if (!$this->isPlatformDependency($dep)) {
                $queue[] = $dep;
            }
        }
    }

    return $resolved;
}

private function isPlatformDependency(string $name): bool
{
    return str_starts_with($name, 'php')
        || str_starts_with($name, 'ext-')
        || str_starts_with($name, 'lib-')
        || $name === 'composer-plugin-api';
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Loader/PluginAutoloaderTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add -u
git commit -m "feat(plugin): add dependency resolution to PluginAutoloader"
```

---

## Task 4: Add register() Method

**Files:**
- Modify: `src/Plugin/Loader/PluginAutoloader.php`
- Modify: `tests/Unit/Plugin/Loader/PluginAutoloaderTest.php`

**Step 1: Write test for register**

```php
public function testRegisterOnlyRegistersOnce(): void
{
    $autoloader = new PluginAutoloader();

    $packages = [
        [
            'name' => 'acme/plugin',
            'autoload' => ['psr-4' => ['Acme\\' => 'src/']],
            'install-path' => '../acme/plugin',
        ],
    ];

    $autoloader->register('/tmp/project', ['acme/plugin'], $packages);
    $autoloader->register('/tmp/project', ['acme/plugin'], $packages);

    // If it registered twice, we'd have duplicate autoloaders
    // Check internal state via reflection
    $reflection = new \ReflectionClass($autoloader);
    $prop = $reflection->getProperty('registered');

    self::assertTrue($prop->getValue($autoloader));
}

public function testRegisterDoesNothingWhenNoPlugins(): void
{
    $autoloader = new PluginAutoloader();

    $autoloader->register('/tmp/project', [], []);

    $reflection = new \ReflectionClass($autoloader);
    $prop = $reflection->getProperty('registered');

    self::assertFalse($prop->getValue($autoloader));
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Plugin/Loader/PluginAutoloaderTest.php`
Expected: FAIL with "method register not found"

**Step 3: Implement register method**

```php
/**
 * @param list<string> $pluginPackageNames
 * @param list<array<string, mixed>> $installedPackages
 */
public function register(
    string $projectRoot,
    array $pluginPackageNames,
    array $installedPackages,
): void {
    if ($this->registered) {
        return;
    }

    $relevantPackages = $this->resolveWithDependencies(
        $pluginPackageNames,
        $installedPackages,
    );

    $vendorDir = $projectRoot . '/vendor/composer';

    foreach ($relevantPackages as $package) {
        $this->addPackageMappings($package, $vendorDir);
    }

    if (!empty($this->prefixPaths)) {
        spl_autoload_register($this->loadClass(...));
        $this->registered = true;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Loader/PluginAutoloaderTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add -u
git commit -m "feat(plugin): add register method to PluginAutoloader"
```

---

## Task 5: Integrate into ComposerPluginLoader

**Files:**
- Modify: `src/Plugin/Loader/ComposerPluginLoader.php`
- Modify: `tests/Unit/Plugin/Loader/ComposerPluginLoaderTest.php`

**Step 1: Write integration test**

```php
public function testLoadRegistersAutoloaderForPluginDependencies(): void
{
    // Create a mock project structure with a seaman-plugin
    $projectRoot = sys_get_temp_dir() . '/composer-loader-test-' . uniqid();
    mkdir($projectRoot . '/vendor/composer', 0777, true);
    mkdir($projectRoot . '/vendor/acme/test-plugin/src', 0777, true);

    // Create installed.json
    $installed = [
        'packages' => [
            [
                'name' => 'acme/test-plugin',
                'type' => 'seaman-plugin',
                'install-path' => '../acme/test-plugin',
                'autoload' => [
                    'psr-4' => [
                        'Acme\\TestPlugin\\' => 'src/',
                    ],
                ],
                'extra' => [
                    'seaman' => [
                        'plugin-class' => 'Acme\\TestPlugin\\TestPlugin',
                    ],
                ],
            ],
        ],
    ];
    file_put_contents(
        $projectRoot . '/vendor/composer/installed.json',
        json_encode($installed)
    );

    // Create plugin class
    file_put_contents(
        $projectRoot . '/vendor/acme/test-plugin/src/TestPlugin.php',
        <<<'PHP'
        <?php
        namespace Acme\TestPlugin;
        use Seaman\Plugin\PluginInterface;
        class TestPlugin implements PluginInterface {
            public function name(): string { return 'test'; }
            public function version(): string { return '1.0.0'; }
            public function description(): string { return 'Test'; }
        }
        PHP
    );

    $loader = new ComposerPluginLoader($projectRoot);
    $plugins = $loader->load();

    self::assertCount(1, $plugins);
    self::assertSame('test', $plugins[0]->name());

    // Cleanup
    // ... recursive delete
}
```

**Step 2: Run test to verify current behavior**

Run: `./vendor/bin/pest tests/Unit/Plugin/Loader/ComposerPluginLoaderTest.php`
Expected: FAIL (class not found without autoloader)

**Step 3: Modify ComposerPluginLoader to use PluginAutoloader**

In `load()` method, after finding seaman-plugin packages:

```php
// Register autoloader BEFORE attempting class_exists
$autoloader = new PluginAutoloader();
$autoloader->register(
    $this->projectRoot,
    array_column($pluginPackages, 'name'),
    $packages,
);
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Plugin/Loader/ComposerPluginLoaderTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add -u
git commit -m "feat(plugin): integrate PluginAutoloader into ComposerPluginLoader"
```

---

## Task 6: Run Full Test Suite and Quality Checks

**Step 1: Run all tests**

Run: `./vendor/bin/pest`
Expected: All tests pass

**Step 2: Run PHPStan**

Run: `./vendor/bin/phpstan analyse`
Expected: No errors at level 10

**Step 3: Run php-cs-fixer**

Run: `./vendor/bin/php-cs-fixer fix`
Expected: Code style fixed

**Step 4: Final commit**

```bash
git add -u
git commit -m "chore: fix code style and pass all quality checks"
```

---

## Task 7: Ensure plugin:install Works with Project Composer

**Files:**
- Modify: `src/Command/Plugin/PluginInstallCommand.php`
- Modify: `tests/Unit/Command/Plugin/PluginInstallCommandTest.php` (if exists)

**Step 1: Write test for project validation**

```php
public function testInstallFailsWhenNoComposerJsonExists(): void
{
    $tempDir = sys_get_temp_dir() . '/no-composer-' . uniqid();
    mkdir($tempDir);
    chdir($tempDir);

    // Run command...
    // Should fail with clear error message

    rmdir($tempDir);
}
```

**Step 2: Run test to verify current behavior**

Run: `./vendor/bin/pest tests/Unit/Command/Plugin/PluginInstallCommandTest.php`

**Step 3: Add project validation and explicit working directory**

```php
private function installPackage(string $package, bool $isDev): int
{
    $projectRoot = (string) getcwd();
    $composerJson = $projectRoot . '/composer.json';

    if (!file_exists($composerJson)) {
        Terminal::error('No composer.json found. Run this command from your Symfony project directory.');
        return Command::FAILURE;
    }

    // Validate that the package is a seaman-plugin
    if (!$this->isValidPlugin($package)) {
        Terminal::error(sprintf(
            'Package "%s" is not a valid seaman-plugin or does not exist on Packagist',
            $package,
        ));
        return Command::FAILURE;
    }

    Terminal::info(sprintf('Installing plugin: %s', $package));

    $command = ['composer', 'require', $package];
    if ($isDev) {
        $command[] = '--dev';
    }

    $process = new Process($command, $projectRoot);  // Explicit working directory
    $process->setTimeout(300);
    $process->setTty(Process::isTtySupported());

    // ... rest unchanged
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Command/Plugin/PluginInstallCommandTest.php`

**Step 5: Commit**

```bash
git add -u
git commit -m "fix(plugin): validate project and set explicit working directory for plugin:install"
```

---

## Task 8: Manual PHAR Test

**Step 1: Compile PHAR**

Run: `./bin/box compile`

**Step 2: Create test project with Composer plugin**

```bash
mkdir /tmp/phar-plugin-test
cd /tmp/phar-plugin-test
composer init --name=test/project --no-interaction
# Create a mock seaman-plugin package manually or use a real one
```

**Step 3: Test plugin loading**

Run: `/path/to/seaman.phar plugin:list`
Expected: Shows Composer-installed plugin

**Step 4: Document results**

If successful, update docs with instructions for plugin authors.
