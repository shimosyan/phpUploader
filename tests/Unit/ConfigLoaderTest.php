<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUploader\Core\ConfigLoader;

final class ConfigLoaderTest extends TestCase
{
    private ?string $originalConfigPath = null;

    protected function setUp(): void
    {
        $value = getenv('PHPUPLOADER_CONFIG_PATH');
        $this->originalConfigPath = is_string($value) ? $value : null;
        putenv('PHPUPLOADER_CONFIG_PATH');
    }

    protected function tearDown(): void
    {
        if ($this->originalConfigPath === null) {
            putenv('PHPUPLOADER_CONFIG_PATH');
            return;
        }

        putenv('PHPUPLOADER_CONFIG_PATH=' . $this->originalConfigPath);
    }

    public function testResolvesDefaultConfigPath(): void
    {
        self::assertSame(
            '/app/config/config.php',
            ConfigLoader::resolvePath('/app')
        );
    }

    public function testResolvesRelativeOverridePathFromBaseDirectory(): void
    {
        putenv('PHPUPLOADER_CONFIG_PATH=.test-runtime/config/config.php');

        self::assertSame(
            '/app/.test-runtime/config/config.php',
            ConfigLoader::resolvePath('/app')
        );
    }

    public function testResolvesAbsoluteOverridePathAsIs(): void
    {
        putenv('PHPUPLOADER_CONFIG_PATH=/tmp/phpuploader/config.php');

        self::assertSame(
            '/tmp/phpuploader/config.php',
            ConfigLoader::resolvePath('/app')
        );
    }
}
