<?php

// ABOUTME: Tests for NamespaceTransformer service.
// ABOUTME: Verifies namespace transformation in PHP files.

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Export;

use PHPUnit\Framework\TestCase;
use Seaman\Plugin\Export\NamespaceTransformer;

final class NamespaceTransformerTest extends TestCase
{
    private NamespaceTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new NamespaceTransformer();
    }

    public function test_transforms_namespace_declaration(): void
    {
        $content = <<<'PHP'
<?php

declare(strict_types=1);

namespace Seaman\LocalPlugins\MyPlugin;

class MyPlugin {}
PHP;

        $expected = <<<'PHP'
<?php

declare(strict_types=1);

namespace Diego\MyPlugin;

class MyPlugin {}
PHP;

        $result = $this->transformer->transform(
            $content,
            'Seaman\LocalPlugins\MyPlugin',
            'Diego\MyPlugin',
        );

        $this->assertSame($expected, $result);
    }

    public function test_transforms_use_statements(): void
    {
        $content = <<<'PHP'
<?php

namespace Seaman\LocalPlugins\MyPlugin;

use Seaman\LocalPlugins\MyPlugin\Command\MyCommand;
use Seaman\LocalPlugins\MyPlugin\Service\Helper;
use Some\Other\Class;

class MyPlugin {}
PHP;

        $expected = <<<'PHP'
<?php

namespace Diego\MyPlugin;

use Diego\MyPlugin\Command\MyCommand;
use Diego\MyPlugin\Service\Helper;
use Some\Other\Class;

class MyPlugin {}
PHP;

        $result = $this->transformer->transform(
            $content,
            'Seaman\LocalPlugins\MyPlugin',
            'Diego\MyPlugin',
        );

        $this->assertSame($expected, $result);
    }

    public function test_transforms_fully_qualified_class_references(): void
    {
        $content = <<<'PHP'
<?php

namespace Seaman\LocalPlugins\MyPlugin;

class MyPlugin
{
    public function test(): \Seaman\LocalPlugins\MyPlugin\Service\Helper
    {
        return new \Seaman\LocalPlugins\MyPlugin\Service\Helper();
    }
}
PHP;

        $expected = <<<'PHP'
<?php

namespace Diego\MyPlugin;

class MyPlugin
{
    public function test(): \Diego\MyPlugin\Service\Helper
    {
        return new \Diego\MyPlugin\Service\Helper();
    }
}
PHP;

        $result = $this->transformer->transform(
            $content,
            'Seaman\LocalPlugins\MyPlugin',
            'Diego\MyPlugin',
        );

        $this->assertSame($expected, $result);
    }

    public function test_transforms_namespace_in_docblocks(): void
    {
        $content = <<<'PHP'
<?php

namespace Seaman\LocalPlugins\MyPlugin;

/**
 * @return \Seaman\LocalPlugins\MyPlugin\Service\Helper
 */
class MyPlugin
{
    /**
     * @param \Seaman\LocalPlugins\MyPlugin\Service\Helper $helper
     */
    public function test($helper): void {}
}
PHP;

        $expected = <<<'PHP'
<?php

namespace Diego\MyPlugin;

/**
 * @return \Diego\MyPlugin\Service\Helper
 */
class MyPlugin
{
    /**
     * @param \Diego\MyPlugin\Service\Helper $helper
     */
    public function test($helper): void {}
}
PHP;

        $result = $this->transformer->transform(
            $content,
            'Seaman\LocalPlugins\MyPlugin',
            'Diego\MyPlugin',
        );

        $this->assertSame($expected, $result);
    }

    public function test_does_not_transform_partial_matches(): void
    {
        $content = <<<'PHP'
<?php

namespace Seaman\LocalPlugins\MyPlugin;

use Seaman\LocalPlugins\MyPluginExtension\SomeClass;

class MyPlugin {}
PHP;

        $expected = <<<'PHP'
<?php

namespace Diego\MyPlugin;

use Seaman\LocalPlugins\MyPluginExtension\SomeClass;

class MyPlugin {}
PHP;

        $result = $this->transformer->transform(
            $content,
            'Seaman\LocalPlugins\MyPlugin',
            'Diego\MyPlugin',
        );

        $this->assertSame($expected, $result);
    }

    public function test_preserves_file_without_target_namespace(): void
    {
        $content = <<<'PHP'
<?php

namespace Some\Other\Namespace;

class SomeClass {}
PHP;

        $result = $this->transformer->transform(
            $content,
            'Seaman\LocalPlugins\MyPlugin',
            'Diego\MyPlugin',
        );

        $this->assertSame($content, $result);
    }
}
