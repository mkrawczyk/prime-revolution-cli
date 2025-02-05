<?php

namespace Mkrawczyk\PrimeRevolutionCli\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Mkrawczyk\PrimeRevolutionCli\Models\PrimeCard;
use ZipArchive;

class FetchRevolution extends Command {

    private const QUERY_URL_BASE = 'http://prime.e-wrestling.org/content.php';

    /**
     * Name of the command that we are building.
     */
    protected static $defaultName = 'fetch';

    /**
     * Command description as it appears in the list of available commands.
     */
    protected static $defaultDescription = 'Fetch all PRIME Revolution/supercard shows.';

    private string $temporaryOutputDirectory;
    private Filesystem $filesystem;

    public function __construct(
        private Client $client
    ) {
        parent::__construct();

        $this->filesystem = new Filesystem();
    }

    /**
     * @throws GuzzleException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startTime = time();

        $this->temporaryOutputDirectory = '/tmp/prime-revolution-cli/' . $startTime;
        if (! $this->filesystem->exists($this->temporaryOutputDirectory)) {
            $this->filesystem->mkdir($this->temporaryOutputDirectory);
        }
        echo 'Creating temporary output directory ' . $this->temporaryOutputDirectory . PHP_EOL;

        $showList = $this->getShowListFromArchive();
        foreach ($showList as $show) {
            $this->getShowByID($show['id']);
            echo 'Processing PRIME show with ID ' . $show['id'] . PHP_EOL;
        }

        $finder = new Finder();
        $finder->files()->in($this->temporaryOutputDirectory);
        if ($finder->hasResults()) {
            $zipArchive = new ZipArchive();

            $homepath = $_SERVER['HOME'];
            $outputBaseDirectory = $homepath . '/prime-revolution-cli';

            if (! $this->filesystem->exists($outputBaseDirectory)) {
                $this->filesystem->mkdir($outputBaseDirectory);
            }
            $outputArchive = $outputBaseDirectory . '/prime-revolution-archive.'
                . date('Y-m-d_H-i-s', time()) . '.zip';
            $zipArchive->open($outputArchive, ZipArchive::CREATE);

            foreach ($finder as $file) {
                $zipArchive->addFile($file->getRealPath(), $file->getRelativePathname());
            }
            $zipArchive->close();

            // We need to do a second iteration, because cleaning up the files must occur AFTER we call
            // ::close() on the ZipArchive, otherwise nothing gets written to it.
            foreach ($finder as $file) {
                $this->filesystem->remove($file->getRealPath());
            }
            $this->filesystem->remove($this->temporaryOutputDirectory);
        }

        return 0;
    }

    /**
     * @return array<int, array{id: int, title: string, showDate: string}>
     *
     * @throws GuzzleException
     */
    private function getShowListFromArchive(): array
    {
        $showList = [];

        $queryParams= [
            'p' => 'archives',
        ];
        $responseBodyAsString = $this->getSiteContent($queryParams);

        $crawler = new Crawler();
        $crawler->addHtmlContent($responseBodyAsString, 'UTF-8');

        // This is an ugly XPath string, but the markup for this page is hideous.
        $xPathQueryBase = '(//table[contains(attribute::class, "archive")]//tr[contains(attribute::class, "archiverow1") or contains(attribute::class, "archiverow2")])';
        $showUrlList = $crawler->filterXPath($xPathQueryBase . '//td[1]//a/@href');
        $showTitleList = $crawler->filterXPath($xPathQueryBase . '//td[1]/a');
        $showDateList = $crawler->filterXPath($xPathQueryBase . '//td[2]');

        for ($i = 0; $i < $showUrlList->count(); $i++) {
            $showUrl = $showUrlList->getNode($i)->textContent;
            $showTitle = $showTitleList->getNode($i)->textContent;
            $showDate = $showDateList->getNode($i)->textContent;

            $urlFragments = explode('=', $showUrl);
            $showID = $urlFragments[count($urlFragments) - 1];

            $showList[] = [
                'id' => $showID,
                'title' => $showTitle,
                'showDate' => $showDate,
            ];
        }

        return $showList;
    }

    /**
     * @throws GuzzleException
     */
    private function getShowByID(int $showID): int
    {
        $queryParams = [
            'p' => 'results',
            'id' => $showID,
        ];
        $responseBodyAsString = $this->getSiteContent($queryParams);

        $crawler = new Crawler();
        // Needed because the original Backstage script was the wild west of allowing stuff into the DB.
        $crawler->addHtmlContent($responseBodyAsString, 'UTF-8');

        $primeCard = new PrimeCard($crawler);
        $primeCard->buildCardFromCrawler();

        $this->buildShowStringFromResponse($primeCard);

        return 0;
    }

    private function buildShowStringFromResponse(
        PrimeCard $card
    ): void {
        $showContentString = $card->getCardAsString();

        $filesystem = new Filesystem();
        $filesystem->appendToFile($this->temporaryOutputDirectory
            . "/{$card->getDateFromLocation()}_{$card->getTitle()}.txt", $showContentString);
    }

    /**
     * @throws GuzzleException
     */
    private function getSiteContent(array $queryParams): string
    {
        try {
            $response = $this->client->request(
                'GET',
                self::QUERY_URL_BASE,
                [
                    'query' => $queryParams,
                    'headers' => [
                        'Accept' => 'text/html; charset=UTF-8',
                        'User-Agent' => 'prime-revolution-cli',
                    ],
                ]
            );

            $responseBodyAsString = $response->getBody()->getContents();
        } catch (ServerException $e) {
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();
        }

        return $responseBodyAsString;
    }
}