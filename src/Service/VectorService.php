<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Chapter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\IndexerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * Scene-level embedding of the corpus.
 *
 * A scene is the natural chunk here: every scene in the corpus fits in one
 * embedding window (the largest, Comedy of Errors 5.1, is ~5.4k tokens), and
 * it is the unit people actually recall ("the scene where the twins finally
 * meet"). A single speech averages 22 words -- far too small to retrieve on
 * its own -- and a whole play is far too big.
 */
final class VectorService
{
    /**
     * Frozen namespace for scene UUIDs. Document ids are UUIDv5 of
     * "workId:section:scene", so the same scene gets the same id on every
     * machine and every run -- the store upserts instead of duplicating, and
     * re-embedding after a database purge is idempotent rather than additive.
     */
    private const NAMESPACE = 'c549a663-c7ea-4e5d-85be-3c4cdb355c81';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IndexerInterface $indexer,
    ) {}

    #[AsCommand('app:vectorize', 'embed works as scene-level chunks')]
    public function vectorize(
        SymfonyStyle $io,
        #[Argument('work to embed (e.g. comedyerrors); omit for the whole corpus')] ?string $workId = null,
        #[Option('build and show the chunks without calling the embedding model')] bool $dryRun = false,
    ): int {
        $scenes = $this->scenes($workId);
        if ([] === $scenes) {
            $io->error($workId ? "No scenes found for work '$workId'." : 'No scenes found; run app:load first.');

            return Command::FAILURE;
        }

        $documents = array_map($this->toDocument(...), $scenes);

        if ($dryRun) {
            $io->table(
                ['id', 'scene', 'speeches', 'words', 'chars'],
                array_map(static fn (TextDocument $d): array => [
                    $d->getId(),
                    $d->getMetadata()['workId'].' '.$d->getMetadata()['act'],
                    $d->getMetadata()['speechCount'],
                    $d->getMetadata()['wordCount'],
                    mb_strlen($d->getContent()),
                ], $documents),
            );
            $io->note(sprintf('%d chunks, %d characters total. Nothing was embedded.', count($documents), array_sum(array_map(static fn (TextDocument $d): int => mb_strlen($d->getContent()), $documents))));

            return Command::SUCCESS;
        }

        $io->progressStart(count($documents));
        foreach ($documents as $document) {
            $this->indexer->index($document);
            $io->progressAdvance();
        }
        $io->progressFinish();

        $io->success(sprintf('Embedded %d scenes from %s.', count($documents), $workId ?? 'the whole corpus'));

        return Command::SUCCESS;
    }

    /** @return list<Chapter> */
    private function scenes(?string $workId): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('c', 'w', 'p')
            ->from(Chapter::class, 'c')
            ->join('c.work', 'w')
            ->leftJoin('c.paragraphs', 'p')
            ->orderBy('w.id')->addOrderBy('c.section')->addOrderBy('c.scene')
            ->addOrderBy('p.ordinal');

        if (null !== $workId) {
            $qb->where('w.id = :workId')->setParameter('workId', $workId);
        }

        return $qb->getQuery()->getResult();
    }

    private function toDocument(Chapter $scene): TextDocument
    {
        $work = $scene->work;
        $act = sprintf('Act %d, Scene %d', $scene->section, $scene->scene);

        $lines = [sprintf('%s — %s', $work->title, $act)];
        if (null !== $scene->description) {
            $lines[] = $scene->description;
        }
        $lines[] = '';

        $speakers = [];
        $words = 0;
        foreach ($scene->paragraphs as $paragraph) {
            $speaker = $paragraph->character?->name ?? $paragraph->speakerCode;
            if (null !== $speaker) {
                $speakers[$speaker] = true;
                $lines[] = mb_strtoupper($speaker);
            }
            $lines[] = $paragraph->text;
            $lines[] = '';
            $words += $paragraph->wordCount;
        }

        $content = trim(implode("\n", $lines));

        $metadata = new Metadata([
            'workId' => $work->id,
            'title' => $work->title,
            'genre' => $work->genre->value,
            'section' => $scene->section,
            'scene' => $scene->scene,
            'act' => $act,
            'chapterId' => $scene->id,
            'speakers' => array_keys($speakers),
            'speechCount' => count($scene->paragraphs),
            'wordCount' => $words,
        ]);
        // Drives the store's hybrid query, which runs to_tsvector over metadata->>'_text'.
        $metadata->setText($content);
        $metadata->setTitle(sprintf('%s, %s', $work->title, $act));

        return new TextDocument(
            id: Uuid::v5(Uuid::fromString(self::NAMESPACE), sprintf('%s:%d:%d', $work->id, $scene->section, $scene->scene))->toRfc4122(),
            content: $content,
            metadata: $metadata,
        );
    }
}
