<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;
use Survos\FetchBundle\Service\ChunkDownloader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Zenstruck\Bytes;
use ZipArchive;

/**
 * The Open Source Shakespeare distribution, kept as the downloaded zip.
 *
 * Nothing is ever unpacked to disk: the tables are read straight out of the
 * archive through PHP's `zip://` stream wrapper, so the only artefact on disk
 * is the 5.5MB zip rather than 15MB of extracted text.
 */
final class OssArchive
{
    public const string SOURCE_URL = 'https://www.opensourceshakespeare.org/downloads/oss-textdb.zip';

    /** The four core tables the loader needs; WordForms.txt is in the zip too but unused. */
    public const array TABLES = ['Works.txt', 'Chapters.txt', 'Characters.txt', 'Paragraphs.txt'];

    public function __construct(
        private readonly ChunkDownloader $downloader,
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/data/oss-textdb.zip')] private readonly string $archivePath,
    ) {}

    #[AsCommand('app:download', 'download the Open Source Shakespeare archive')]
    public function downloadCommand(
        SymfonyStyle $io,
        #[Option('download again even if the archive is already present')] bool $force = false,
    ): int {
        $this->ensure($io, $force);
        $io->success(sprintf('%s is ready.', $this->archivePath));

        return Command::SUCCESS;
    }

    /**
     * Download the archive unless it is already here, then check that it holds
     * every table the loader expects.
     */
    public function ensure(SymfonyStyle $io, bool $force = false): void
    {
        if ($force || !$this->filesystem->exists($this->archivePath)) {
            $io->writeln(sprintf('Fetching <info>%s</info>', self::SOURCE_URL));
            $bytes = $this->download($io, $force);
            $io->writeln(sprintf('Saved %s to <info>%s</info>', Bytes::parse($bytes), $this->archivePath));
        }

        $this->assertTablesPresent();
    }

    /** Path readable by any stream-aware reader, decompressed on the fly. */
    public function entry(string $table): string
    {
        return 'zip://'.$this->archivePath.'#'.$table;
    }

    private function download(SymfonyStyle $io, bool $force): int
    {
        $bar = null;

        try {
            return $this->downloader->download(
                self::SOURCE_URL,
                $this->archivePath,
                function (int $written, ?int $total, float $bytesPerSecond) use ($io, &$bar): void {
                    if (null === $total) {
                        return;
                    }
                    $bar ??= $this->startBar($io, $total);
                    $bar->setMessage(Bytes::parse((int) $bytesPerSecond).'/s', 'rate');
                    $bar->setProgress(min($written, $total));
                },
                ['overwrite' => $force, 'timeout' => 60.0],
            );
        } finally {
            $bar?->finish();
            $io->newLine(2);
        }
    }

    private function startBar(SymfonyStyle $io, int $total): ProgressBar
    {
        $bar = $io->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %rate%');
        $bar->setMessage('', 'rate');
        $bar->start();

        return $bar;
    }

    private function assertTablesPresent(): void
    {
        $zip = new ZipArchive();
        if (true !== $code = $zip->open($this->archivePath)) {
            throw new RuntimeException(sprintf('Cannot open %s (ZipArchive error %d); delete it and run app:download --force.', $this->archivePath, $code));
        }

        try {
            $missing = array_values(array_filter(
                self::TABLES,
                static fn (string $table): bool => false === $zip->locateName($table),
            ));
        } finally {
            $zip->close();
        }

        if ([] !== $missing) {
            throw new RuntimeException(sprintf('%s is missing %s; run app:download --force.', $this->archivePath, implode(', ', $missing)));
        }
    }
}
