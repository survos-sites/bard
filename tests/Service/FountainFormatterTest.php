<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Chapter;
use App\Entity\Character;
use App\Entity\Paragraph;
use App\Entity\Work;
use App\Enum\Genre;
use App\Service\FountainFormatter;
use PHPUnit\Framework\TestCase;

final class FountainFormatterTest extends TestCase
{
    public function testFormatsSceneDirectionsAndDialogue(): void
    {
        $work = new Work('hamlet', 'Hamlet', 'The Tragedy of Hamlet', 1600, Genre::Tragedy, 'Moby', 1, 1);
        $chapter = new Chapter(1, $work, 1, 1, 'Elsinore. A platform before the castle.');
        $hamlet = new Character('hamlet', 'Hamlet', null, null, 1);
        $direction = new Paragraph(1, $work, $chapter, null, 1, 'xxx', '[Enter HAMLET]', 'b', 14, 2);
        $speech = new Paragraph(2, $work, $chapter, $hamlet, 2, 'hamlet', 'To be,[p]or not to be.', 'd', 22, 6);
        $work->chapters->add($chapter);
        $chapter->paragraphs->add($direction);
        $chapter->paragraphs->add($speech);

        $fountain = (new FountainFormatter())->format($work);

        self::assertStringContainsString('.Elsinore. A platform before the castle.', $fountain);
        self::assertStringContainsString('[Enter HAMLET]', $fountain);
        self::assertStringContainsString("HAMLET\nTo be,\nor not to be.", $fountain);
    }
}
