<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use PHPUploader\Model\Init;

final class InitTest extends TestCase
{
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            $this->removeDirectory($directory);
        }
    }

    public function testInitializeCreatesRuntimeDirectoriesAndDatabaseTables(): void
    {
        $rootDirectory = $this->createTemporaryDirectory();

        $init = new Init([
            'master' => 'test-master-key',
            'key' => 'test-encryption-key',
            'sessionSalt' => 'test-session-salt',
            'dbDirectoryPath' => $rootDirectory . '/db',
            'dataDirectoryPath' => $rootDirectory . '/data',
            'logDirectoryPath' => $rootDirectory . '/logs',
        ]);

        $database = $init->initialize();

        self::assertInstanceOf(PDO::class, $database);
        self::assertDirectoryExists($rootDirectory . '/db');
        self::assertDirectoryExists($rootDirectory . '/data');
        self::assertDirectoryExists($rootDirectory . '/logs');
        self::assertFileExists($rootDirectory . '/db/uploader.db');

        $tables = $database
            ->query("SELECT name FROM sqlite_master WHERE type = 'table'")
            ->fetchAll(PDO::FETCH_COLUMN);

        self::assertContains('uploaded', $tables);
        self::assertContains('access_tokens', $tables);
        self::assertContains('access_logs', $tables);
    }

    private function createTemporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/phpuploader-test-' . bin2hex(random_bytes(8));
        mkdir($directory, 0755, true);
        $this->temporaryDirectories[] = $directory;

        return $directory;
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
