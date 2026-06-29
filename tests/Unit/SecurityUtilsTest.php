<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUploader\Core\SecurityUtils;

final class SecurityUtilsTest extends TestCase
{
    private array $temporaryDirectories = [];

    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            $this->removeDirectory($directory);
        }
    }

    public function testGeneratesAndValidatesCsrfToken(): void
    {
        $firstToken = SecurityUtils::generateCSRFToken();
        $secondToken = SecurityUtils::generateCSRFToken();

        self::assertSame($firstToken, $secondToken);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $firstToken);
        self::assertTrue(SecurityUtils::validateCSRFToken($firstToken));
        self::assertFalse(SecurityUtils::validateCSRFToken('invalid-token'));
        self::assertFalse(SecurityUtils::validateCSRFToken(null));
    }

    public function testSanitizesDangerousFilenameCharacters(): void
    {
        self::assertSame(
            'evilname.pdf',
            SecurityUtils::sanitizeFilename('../evil<>name..pdf')
        );
    }

    public function testEscapesHtmlAndHandlesNull(): void
    {
        self::assertSame('', SecurityUtils::escapeHtml(null));
        self::assertSame('&lt;script&gt;alert(&apos;x&apos;)&lt;/script&gt;', SecurityUtils::escapeHtml("<script>alert('x')</script>"));
    }

    public function testGeneratesSafeFilePathWithoutOverwritingExistingFile(): void
    {
        $uploadDirectory = $this->createTemporaryDirectory();
        file_put_contents($uploadDirectory . '/report.txt', 'existing file');

        self::assertSame(
            $uploadDirectory . '/report_1.txt',
            SecurityUtils::generateSafeFilePath($uploadDirectory, '../report.txt')
        );
    }

    public function testValidateUploadedFileReportsMissingFile(): void
    {
        $errors = SecurityUtils::validateUploadedFile(
            [
                'name' => '',
                'tmp_name' => '',
                'size' => 0,
                'error' => UPLOAD_ERR_NO_FILE,
            ],
            []
        );

        self::assertSame(['ファイルが選択されていません。'], $errors);
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
