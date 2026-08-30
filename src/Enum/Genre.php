<?php

declare(strict_types=1);

namespace App\Enum;

enum Genre: string
{
    case Comedy = 'c';
    case History = 'h';
    case Poem = 'p';
    case Sonnet = 's';
    case Tragedy = 't';

    public function label(): string
    {
        return match ($this) {
            self::Comedy => 'Comedy',
            self::History => 'History',
            self::Poem => 'Poem',
            self::Sonnet => 'Sonnet',
            self::Tragedy => 'Tragedy',
        };
    }
}
