<?php

declare(strict_types=1);

namespace PHPUploader\Core;

final class ConfigLoader
{
    private const CONFIG_ENV = 'PHPUPLOADER_CONFIG_PATH';

    public static function resolvePath(string $baseDir): string
    {
        $configuredPath = getenv(self::CONFIG_ENV);

        if (is_string($configuredPath) && trim($configuredPath) !== '') {
            if (self::isAbsolutePath($configuredPath)) {
                return $configuredPath;
            }

            return rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $configuredPath;
        }

        return rtrim($baseDir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'config'
            . DIRECTORY_SEPARATOR
            . 'config.php';
    }

    public static function requireConfig(string $baseDir): string
    {
        $configPath = self::resolvePath($baseDir);

        if (!file_exists($configPath)) {
            throw new \RuntimeException(
                '設定ファイルが見つかりません。config.php.example を参考に config.php を作成してください。'
            );
        }

        require_once $configPath;

        return $configPath;
    }

    private static function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
