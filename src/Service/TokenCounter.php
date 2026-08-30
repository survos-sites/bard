<?php

declare(strict_types=1);

namespace App\Service;

use Yethee\Tiktoken\Encoder;
use Yethee\Tiktoken\EncoderProvider;

final class TokenCounter
{
    public const ENCODING = 'cl100k_base';

    private ?Encoder $encoder = null;

    public function count(string $text): int
    {
        return count($this->encoder()->encode($text));
    }

    private function encoder(): Encoder
    {
        return $this->encoder ??= (new EncoderProvider())->get(self::ENCODING);
    }
}
