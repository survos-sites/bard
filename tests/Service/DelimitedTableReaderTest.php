<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\DelimitedTableReader;
use PHPUnit\Framework\TestCase;

final class DelimitedTableReaderTest extends TestCase
{
    public function testReadsMultilineOssRecords(): void
    {
        $reader = new DelimitedTableReader();
        $path = dirname(__DIR__, 2).'/data/oss/Paragraphs.txt';
        $rows = $reader->read($path);
        $first = $rows->current();

        self::assertSame('12night', $first['WorkID']);
        self::assertSame('630863', $first['ParagraphID']);
        self::assertStringContainsString('DUKE ORSINO', (string) $first['PlainText']);
        self::assertCount(35_465, iterator_to_array($reader->read($path)));
    }
}
