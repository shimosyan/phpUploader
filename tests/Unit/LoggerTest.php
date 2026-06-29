<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUploader\Core\Logger;

final class LoggerTest extends TestCase
{
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            $this->removeDirectory($directory);
        }
    }

    public function testWritesFileLogWithoutDatabaseConnection(): void
    {
        $logDirectory = $this->createTemporaryDirectory();
        $logger = new Logger($logDirectory, Logger::LOG_DEBUG);

        $logger->info('Application started', ['request_id' => 'test-1']);

        $logContents = $this->readOnlyLogFile($logDirectory);
        self::assertStringContainsString('[info] Application started', $logContents);
        self::assertStringContainsString('"request_id":"test-1"', $logContents);
    }

    public function testNormalizesUppercaseLogLevel(): void
    {
        $logDirectory = $this->createTemporaryDirectory();
        $logger = new Logger($logDirectory, 'ERROR');

        $logger->info('This should be ignored');
        $logger->error('This should be written');

        $logContents = $this->readOnlyLogFile($logDirectory);
        self::assertStringNotContainsString('This should be ignored', $logContents);
        self::assertStringContainsString('[error] This should be written', $logContents);
    }

    private function createTemporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/phpuploader-test-' . bin2hex(random_bytes(8));
        mkdir($directory, 0755, true);
        $this->temporaryDirectories[] = $directory;

        return $directory;
    }

    private function readOnlyLogFile(string $logDirectory): string
    {
        $logFiles = glob($logDirectory . '/*.log');

        self::assertIsArray($logFiles);
        self::assertCount(1, $logFiles);

        return (string)file_get_contents($logFiles[0]);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
