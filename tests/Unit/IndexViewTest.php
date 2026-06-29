<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class IndexViewTest extends TestCase
{
    public function testFileDataJsonUsesHexEscapingForInlineScriptSafety(): void
    {
        $data = [
            [
                'id' => 1,
                'origin_file_name' => '</script><script>alert("x")</script>.pdf',
                'comment' => "Tom & Jerry's file",
            ],
        ];

        $json = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        self::assertIsString($json);
        self::assertStringNotContainsString('</script>', $json);
        self::assertStringContainsString('\\u003C\\/script\\u003E', $json);
        self::assertStringContainsString('\\u0026', $json);
        self::assertStringContainsString('\\u0027', $json);
        self::assertStringContainsString('\\u0022', $json);
    }
}
