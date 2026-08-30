<?php

declare(strict_types=1);

namespace App\Service;

use Generator;
use RuntimeException;
use SplFileObject;

final class DelimitedTableReader
{
    /** @return Generator<int, array<string, string|null>> */
    public function read(string $path): Generator
    {
        $file = new SplFileObject($path, 'r');
        $file->setCsvControl(',', '~', '');

        $header = $file->fgetcsv();
        if (!is_array($header)) {
            throw new RuntimeException("Unable to read header from $path");
        }

        $header = array_map(static fn (mixed $value): string => (string) $value, $header);
        while (!$file->eof()) {
            $values = $file->fgetcsv();
            if (!is_array($values) || [null] === $values) {
                continue;
            }
            if (count($values) !== count($header)) {
                throw new RuntimeException(sprintf('%s:%d has %d columns; expected %d.', $path, $file->key() + 1, count($values), count($header)));
            }

            yield array_combine($header, $values);
        }
    }
}
