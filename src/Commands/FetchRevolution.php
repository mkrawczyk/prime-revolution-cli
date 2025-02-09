<?php

namespace Mkrawczyk\PrimeRevolutionCli\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Mkrawczyk\PrimeRevolutionCli\Models\PrimeCard;
use Mkrawczyk\PrimeRevolutionCli\Services\ContentService;
use Mkrawczyk\PrimeRevolutionCli\Services\FileSystemService;
use ZipArchive;

class FetchRevolution extends Command {

    private const QUERY_URL_BASE = 'http://prime.e-wrestling.org/content.php';

    // This is an ugly XPath string, but the markup for this page is very much a product of the mid-2000s.
    private const XPATH_QUERY_BASE = '(//table[contains(attribute::class, "archive")]//'
        . 'tr[contains(attribute::class, "archiverow1") or contains(attribute::class, "archiverow2")])';

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
            // Move this function into the FileSystemService
            $fileSystemService->createFileFromPrimeCard($primeCard);
        }

        $fileSystemService->createZipArchiveFromFinder();

        return 0;
    }
}