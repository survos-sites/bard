<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Chapter;
use App\Entity\Character;
use App\Entity\Paragraph;
use App\Entity\Work;
use App\Enum\Genre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

final class AppService
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DelimitedTableReader $reader,
        #[Autowire('%kernel.project_dir%/data/oss')] private readonly string $dataDir,
    ) {}

    #[AsCommand('app:load', 'load the Open Source Shakespeare corpus')]
    public function load(SymfonyStyle $io): int
    {
        if (!$this->dataIsAvailable()) {
            $io->error([
                'The Open Source Shakespeare source files are not installed.',
                'Download oss-textdb.zip from https://www.opensourceshakespeare.org/downloads/ and extract the four core tables into data/oss/.',
            ]);

            return Command::FAILURE;
        }

        $connection = $this->em->getConnection();
        $connection->executeStatement('TRUNCATE bard_paragraph, bard_chapter, bard_character, bard_work CASCADE');

        $this->loadWorks();
        $this->loadCharacters();
        $chapterLocations = $this->loadChapters();
        $paragraphCount = $this->loadParagraphs($chapterLocations, $io);

        $io->success(sprintf(
            'Loaded %d works, %d chapters, %d characters, and %d paragraphs.',
            $this->countRows('Works.txt'),
            count($chapterLocations),
            $this->countRows('Characters.txt'),
            $paragraphCount,
        ));

        return Command::SUCCESS;
    }

    private function loadWorks(): void
    {
        foreach ($this->reader->read($this->path('Works.txt')) as $row) {
            $this->em->persist(new Work(
                id: (string) $row['WorkID'],
                title: (string) $row['Title'],
                longTitle: (string) $row['LongTitle'],
                year: (int) $row['Date'],
                genre: Genre::from((string) $row['GenreType']),
                source: $this->nullable($row['Source']),
                totalWords: (int) $row['TotalWords'],
                totalParagraphs: (int) $row['TotalParagraphs'],
            ));
        }
        $this->em->flush();
        $this->em->clear();
    }

    private function loadCharacters(): void
    {
        foreach ($this->reader->read($this->path('Characters.txt')) as $row) {
            $this->em->persist(new Character(
                id: (string) $row['CharID'],
                name: (string) $row['CharName'],
                abbreviation: $this->nullable($row['Abbrev']),
                description: $this->nullable($row['Description']),
                speechCount: (int) $row['SpeechCount'],
            ));
        }
        $this->em->flush();
        $this->em->clear();
    }

    /** @return array<string, int> */
    private function loadChapters(): array
    {
        $locations = [];
        foreach ($this->reader->read($this->path('Chapters.txt')) as $row) {
            $id = (int) $row['ChapterID'];
            $workId = (string) $row['WorkID'];
            $section = (int) $row['Section'];
            $scene = (int) $row['Chapter'];
            $locations[$this->chapterKey($workId, $section, $scene)] = $id;

            $this->em->persist(new Chapter(
                id: $id,
                work: $this->em->getReference(Work::class, $workId),
                section: $section,
                scene: $scene,
                description: $this->cleanText($row['Description']),
            ));
        }
        $this->em->flush();
        $this->em->clear();

        return $locations;
    }

    /** @param array<string, int> $chapterLocations */
    private function loadParagraphs(array $chapterLocations, SymfonyStyle $io): int
    {
        $characterIds = array_fill_keys(array_map(
            static fn (array $row): string => (string) $row['CharID'],
            iterator_to_array($this->reader->read($this->path('Characters.txt'))),
        ), true);
        $count = 0;

        foreach ($this->reader->read($this->path('Paragraphs.txt')) as $row) {
            $workId = (string) $row['WorkID'];
            $key = $this->chapterKey($workId, (int) $row['Section'], (int) $row['Chapter']);
            $chapterId = $chapterLocations[$key] ?? null;
            if (null === $chapterId) {
                $io->warning("Skipping paragraph {$row['ParagraphID']}: no chapter for $key");
                continue;
            }

            $speakerCode = $this->nullable($row['CharID']);
            $this->em->persist(new Paragraph(
                id: (int) $row['ParagraphID'],
                work: $this->em->getReference(Work::class, $workId),
                chapter: $this->em->getReference(Chapter::class, $chapterId),
                character: $speakerCode && isset($characterIds[$speakerCode]) ? $this->em->getReference(Character::class, $speakerCode) : null,
                ordinal: (int) $row['ParagraphNum'],
                speakerCode: $speakerCode,
                text: $this->cleanText($row['PlainText']) ?? '',
                type: $this->nullable($row['ParagraphType']),
                characterCount: (int) $row['CharCount'],
                wordCount: (int) $row['WordCount'],
            ));

            ++$count;
            if (0 === $count % self::BATCH_SIZE) {
                $this->em->flush();
                $this->em->clear();
                $io->isVerbose() && $io->writeln("Loaded $count paragraphs");
            }
        }
        $this->em->flush();
        $this->em->clear();

        return $count;
    }

    private function path(string $filename): string
    {
        return $this->dataDir.'/'.$filename;
    }

    private function dataIsAvailable(): bool
    {
        $expected = ['Chapters.txt', 'Characters.txt', 'Paragraphs.txt', 'Works.txt'];
        $available = [];

        if (is_dir($this->dataDir)) {
            foreach ((new Finder())->in($this->dataDir)->files()->depth(0)->name('*.txt') as $file) {
                $available[] = $file->getFilename();
            }
        }

        return [] === array_diff($expected, $available);
    }

    private function chapterKey(string $workId, int $section, int $scene): string
    {
        return "$workId:$section:$scene";
    }

    private function nullable(?string $value): ?string
    {
        $value = null === $value ? '' : trim($value);

        return '' === $value ? null : $value;
    }

    private function cleanText(?string $value): ?string
    {
        $value = $this->nullable($value);

        return null === $value ? null : html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function countRows(string $filename): int
    {
        return iterator_count($this->reader->read($this->path($filename)));
    }
}
