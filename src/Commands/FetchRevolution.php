<?php

namespace Mkrawczyk\PrimeRevolutionCli\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Mkrawczyk\PrimeRevolutionCli\Services\ContentService;
use Mkrawczyk\PrimeRevolutionCli\Services\FileSystemService;
use ZipArchive;

class FetchRevolution extends Command {

    /**
     * Name of the command that we are building.
     */
    protected static $defaultName = 'fetch';

    /**
     * Command description as it appears in the list of available commands.
     */
    protected static $defaultDescription = 'Fetch all PRIME Revolution/supercard shows.';

    /**
     * @throws GuzzleException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $contentService = new ContentService(new Client(), new Crawler());
        $fileSystemService = new FileSystemService(
            new Filesystem(),
            new Finder(),
            new ZipArchive()
        );

        $fileSystemService->setTemporaryOutputDirectory();

        $showList = $contentService->getShowListFromArchive();
        foreach ($showList as $show) {
            echo 'Processing PRIME show with ID ' . $show['id'] . PHP_EOL;

            $primeCard = $contentService->getPrimeCardByID($show['id']);

            $fileSystemService->createFileFromPrimeCard($primeCard);
        }

        $fileSystemService->createZipArchiveFromFinder();

        return 0;
    }
}