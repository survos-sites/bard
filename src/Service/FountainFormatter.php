<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Paragraph;
use App\Entity\Work;

final class FountainFormatter
{
    public function format(Work $work): string
    {
        $lines = [
            'Title: '.$work->longTitle,
            'Credit: William Shakespeare',
            'Source: Open Source Shakespeare (opensourceshakespeare.org)',
            '',
        ];

        foreach ($work->chapters as $chapter) {
            $heading = trim((string) $chapter->description);
            $lines[] = '.'.($heading ?: sprintf('Act %d, Scene %d', $chapter->section, $chapter->scene));
            $lines[] = '';

            foreach ($chapter->paragraphs as $paragraph) {
                $this->appendParagraph($lines, $paragraph);
            }
        }

        return implode("\n", $lines)."\n";
    }

    /** @param list<string> $lines */
    private function appendParagraph(array &$lines, Paragraph $paragraph): void
    {
        $text = trim(str_replace('[p]', "\n", $paragraph->text));
        if ('' === $text) {
            return;
        }

        if ('xxx' === strtolower((string) $paragraph->speakerCode) || 'b' === $paragraph->type) {
            $lines[] = $text;
            $lines[] = '';
            return;
        }

        $lines[] = strtoupper($paragraph->character?->name ?? $paragraph->speakerCode ?? 'UNKNOWN');
        $lines[] = $text;
        $lines[] = '';
    }
}
