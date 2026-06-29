<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUploader\Model\Index;

final class IndexModelTest extends TestCase
{
    public function testPublicFileColumnsExcludeSensitiveDatabaseFields(): void
    {
        $publicColumns = Index::PUBLIC_FILE_COLUMNS;

        self::assertSame(
            [
                'id',
                'origin_file_name',
                'comment',
                'size',
                'count',
                'input_date',
            ],
            $publicColumns
        );

        self::assertNotContains('ip_address', $publicColumns);
        self::assertNotContains('dl_key_hash', $publicColumns);
        self::assertNotContains('del_key_hash', $publicColumns);
        self::assertNotContains('stored_file_name', $publicColumns);
        self::assertNotContains('file_hash', $publicColumns);
    }
}
