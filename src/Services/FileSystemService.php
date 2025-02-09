<?php

declare(strict_types=1);

namespace Mkrawczyk\PrimeRevolutionCli\Services;

use Mkrawczyk\PrimeRevolutionCli\Models\PrimeCard;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use ZipArchive;

class FileSystemService {

    private string $temporaryOutputDirectory;

    public function __construct(
        private Filesystem $filesystem,
        private Finder $finder,
        private ZipArchive $zipArchive
    ) {}

    public function setTemporaryOutputDirectory(): void
    {
        $this->temporaryOutputDirectory = '/tmp/prime-revolution-cli/' . time();
        if (! $this->filesystem->exists($this->temporaryOutputDirectory)) {
            echo 'Creating temporary output directory ' . $this->temporaryOutputDirectory . PHP_EOL;

            $this->filesystem->mkdir($this->temporaryOutputDirectory);
        }
    }

    public function createFileFromPrimeCard(
        PrimeCard $card
    ): void {
        $showContentString = $card->getCardAsString();

        $this->filesystem->appendToFile($this->temporaryOutputDirectory
            . "/{$card->getDateFromLocation()}_{$card->getTitle()}.txt", $showContentString);
    }

    public function createZipArchiveFromFinder(): void
    {
        $this->finder->files()->in($this->temporaryOutputDirectory);
        if ($this->finder->hasResults()) {

            $homePath = $_SERVER['HOME'];
            $outputBaseDirectory = $homePath . '/prime-revolution-cli';

            if (! $this->filesystem->exists($outputBaseDirectory)) {
                $this->filesystem->mkdir($outputBaseDirectory);
            }
            $outputArchive = $outputBaseDirectory . '/prime-revolution-archive.'
                . date('Y-m-d_H-i-s', time()) . '.zip';
            $this->zipArchive->open($outputArchive, ZipArchive::CREATE);

            foreach ($this->finder as $file) {
                $this->zipArchive->addFile($file->getRealPath(), $file->getRelativePathname());
            }
            $this->zipArchive->close();

            // We need to do a second iteration, because cleaning up the files must occur AFTER we call
            // ::close() on the ZipArchive, otherwise nothing gets written to it.
            foreach ($this->finder as $file) {
                $this->filesystem->remove($file->getRealPath());
            }
            $this->filesystem->remove($this->temporaryOutputDirectory);
        }
    }
}