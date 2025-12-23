<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Loader;

use Seaman\Plugin\Loader\BundledPluginLoader;

beforeEach(function () {
    $this->testDir = sys_get_temp_dir() . '/seaman-bundled-test-' . uniqid();
    mkdir($this->testDir . '/redis/src', 0755, true);
});

afterEach(function () {
    if (isset($this->testDir) && is_dir($this->testDir)) {
        exec("rm -rf {$this->testDir}");
    }
});

test('returns empty array when plugins directory does not exist', function () {
    $loader = new BundledPluginLoader('/nonexistent/path');
    expect($loader->load())->toBe([]);
});

test('returns empty array when plugins directory is empty', function () {
    $loader = new BundledPluginLoader($this->testDir);
    expect($loader->load())->toBe([]);
});

test('discovers plugin from bundled directory', function () {
    $pluginCode = <<<'PHP'
<?php
declare(strict_types=1);
namespace Seaman\Plugin\TestRedis;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\Config\ConfigSchema;

#[AsSeamanPlugin(name: 'seaman/redis-plugin', version: '1.0.0', description: 'Redis')]
final class RedisPlugin implements PluginInterface
{
    public function getName(): string { return 'seaman/redis-plugin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return 'Redis'; }
    public function configSchema(): ConfigSchema { return ConfigSchema::create(); }
    public function configure(array $values): void {}
}
PHP;
    file_put_contents($this->testDir . '/redis/src/RedisPlugin.php', $pluginCode);

    $loader = new BundledPluginLoader($this->testDir);
    $plugins = $loader->load();

    expect($plugins)->toHaveCount(1);
    expect($plugins[0]->getName())->toBe('seaman/redis-plugin');
});

test('ignores files without AsSeamanPlugin attribute', function () {
    $pluginCode = <<<'PHP'
<?php
declare(strict_types=1);
namespace Seaman\Plugin\Invalid;

final class InvalidPlugin {}
PHP;
    file_put_contents($this->testDir . '/redis/src/InvalidPlugin.php', $pluginCode);

    $loader = new BundledPluginLoader($this->testDir);
    $plugins = $loader->load();

    expect($plugins)->toBeEmpty();
});

test('discovers multiple plugins from different directories', function () {
    mkdir($this->testDir . '/mysql/src', 0755, true);

    $redisPlugin = <<<'PHP'
<?php
declare(strict_types=1);
namespace Seaman\Plugin\TestRedis2;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\Config\ConfigSchema;

#[AsSeamanPlugin(name: 'seaman/redis-plugin', version: '1.0.0', description: 'Redis')]
final class RedisPlugin implements PluginInterface
{
    public function getName(): string { return 'seaman/redis-plugin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return 'Redis'; }
    public function configSchema(): ConfigSchema { return ConfigSchema::create(); }
    public function configure(array $values): void {}
}
PHP;

    $mysqlPlugin = <<<'PHP'
<?php
declare(strict_types=1);
namespace Seaman\Plugin\TestMySQL;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\Config\ConfigSchema;

#[AsSeamanPlugin(name: 'seaman/mysql-plugin', version: '1.0.0', description: 'MySQL')]
final class MySQLPlugin implements PluginInterface
{
    public function getName(): string { return 'seaman/mysql-plugin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return 'MySQL'; }
    public function configSchema(): ConfigSchema { return ConfigSchema::create(); }
    public function configure(array $values): void {}
}
PHP;

    file_put_contents($this->testDir . '/redis/src/RedisPlugin.php', $redisPlugin);
    file_put_contents($this->testDir . '/mysql/src/MySQLPlugin.php', $mysqlPlugin);

    $loader = new BundledPluginLoader($this->testDir);
    $plugins = $loader->load();

    expect($plugins)->toHaveCount(2);
    $names = array_map(fn($p) => $p->getName(), $plugins);
    expect($names)->toContain('seaman/redis-plugin');
    expect($names)->toContain('seaman/mysql-plugin');
});

test('ignores abstract classes', function () {
    $pluginCode = <<<'PHP'
<?php
declare(strict_types=1);
namespace Seaman\Plugin\TestAbstract;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;

#[AsSeamanPlugin(name: 'seaman/abstract-plugin', version: '1.0.0', description: 'Abstract')]
abstract class AbstractPlugin implements PluginInterface {}
PHP;
    file_put_contents($this->testDir . '/redis/src/AbstractPlugin.php', $pluginCode);

    $loader = new BundledPluginLoader($this->testDir);
    $plugins = $loader->load();

    expect($plugins)->toBeEmpty();
});

test('ignores classes that do not implement PluginInterface', function () {
    $pluginCode = <<<'PHP'
<?php
declare(strict_types=1);
namespace Seaman\Plugin\TestNoInterface;

use Seaman\Plugin\Attribute\AsSeamanPlugin;

#[AsSeamanPlugin(name: 'seaman/no-interface-plugin', version: '1.0.0', description: 'No Interface')]
final class NoInterfacePlugin
{
    public function getName(): string { return 'seaman/no-interface-plugin'; }
}
PHP;
    file_put_contents($this->testDir . '/redis/src/NoInterfacePlugin.php', $pluginCode);

    $loader = new BundledPluginLoader($this->testDir);
    $plugins = $loader->load();

    expect($plugins)->toBeEmpty();
});

test('discovers plugin with readonly final class modifier order', function () {
    $pluginCode = <<<'PHP'
<?php
declare(strict_types=1);
namespace Seaman\Plugin\TestReadonlyFinal;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\Config\ConfigSchema;

#[AsSeamanPlugin(name: 'seaman/readonly-final-plugin', version: '1.0.0', description: 'Readonly Final')]
readonly final class ReadonlyFinalPlugin implements PluginInterface
{
    public function getName(): string { return 'seaman/readonly-final-plugin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return 'Readonly Final'; }
    public function configSchema(): ConfigSchema { return ConfigSchema::create(); }
    public function configure(array $values): void {}
}
PHP;
    file_put_contents($this->testDir . '/redis/src/ReadonlyFinalPlugin.php', $pluginCode);

    $loader = new BundledPluginLoader($this->testDir);
    $plugins = $loader->load();

    expect($plugins)->toHaveCount(1);
    expect($plugins[0]->getName())->toBe('seaman/readonly-final-plugin');
});

test('ignores plugins in wrong directory structure', function () {
    // Plugin directly in plugins/redis/ (missing src/ subdirectory)
    $pluginCode = <<<'PHP'
<?php
declare(strict_types=1);
namespace Seaman\Plugin\TestWrongDir;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\Config\ConfigSchema;

#[AsSeamanPlugin(name: 'seaman/wrong-dir-plugin', version: '1.0.0', description: 'Wrong Dir')]
final class WrongDirPlugin implements PluginInterface
{
    public function getName(): string { return 'seaman/wrong-dir-plugin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return 'Wrong Dir'; }
    public function configSchema(): ConfigSchema { return ConfigSchema::create(); }
    public function configure(array $values): void {}
}
PHP;
    // Create in redis/ directly, not in redis/src/
    file_put_contents($this->testDir . '/redis/WrongDirPlugin.php', $pluginCode);

    $loader = new BundledPluginLoader($this->testDir);
    $plugins = $loader->load();

    expect($plugins)->toBeEmpty();
});

test('discovers multiple plugins in same directory', function () {
    $plugin1 = <<<'PHP'
<?php
declare(strict_types=1);
namespace Seaman\Plugin\TestCachePlugin;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\Config\ConfigSchema;

#[AsSeamanPlugin(name: 'seaman/cache-plugin', version: '1.0.0', description: 'Cache')]
final class CachePlugin implements PluginInterface
{
    public function getName(): string { return 'seaman/cache-plugin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return 'Cache'; }
    public function configSchema(): ConfigSchema { return ConfigSchema::create(); }
    public function configure(array $values): void {}
}
PHP;

    $plugin2 = <<<'PHP'
<?php
declare(strict_types=1);
namespace Seaman\Plugin\TestSessionPlugin;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\Config\ConfigSchema;

#[AsSeamanPlugin(name: 'seaman/session-plugin', version: '1.0.0', description: 'Session')]
final class SessionPlugin implements PluginInterface
{
    public function getName(): string { return 'seaman/session-plugin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return 'Session'; }
    public function configSchema(): ConfigSchema { return ConfigSchema::create(); }
    public function configure(array $values): void {}
}
PHP;

    file_put_contents($this->testDir . '/redis/src/CachePlugin.php', $plugin1);
    file_put_contents($this->testDir . '/redis/src/SessionPlugin.php', $plugin2);

    $loader = new BundledPluginLoader($this->testDir);
    $plugins = $loader->load();

    expect($plugins)->toHaveCount(2);
    $names = array_map(fn($p) => $p->getName(), $plugins);
    expect($names)->toContain('seaman/cache-plugin');
    expect($names)->toContain('seaman/session-plugin');
});
