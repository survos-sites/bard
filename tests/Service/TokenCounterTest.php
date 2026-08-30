<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\TokenCounter;
use PHPUnit\Framework\TestCase;

final class TokenCounterTest extends TestCase
{
    public function testCountsCl100kTokens(): void
    {
        self::assertSame(3, (new TokenCounter())->count('Hello world!'));
    }
}
