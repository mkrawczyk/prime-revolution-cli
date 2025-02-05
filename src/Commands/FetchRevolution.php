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

        $showList = $this->getShowListFromArchive();
        foreach ($showList as $show) {
            $this->getShowByID($show['id']);
        }

//        $finder = new Finder();
//        $finder->files($this->temporaryOutputDirectory);
//        if ($finder->hasResults()) {
//
//        }

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

        $showTitle = $crawler->filterXPath('//div[contains(attribute::class, "results")]//h1')
            ->getNode(0)
            ->textContent;

        $showLocation = $crawler->filterXPath('//div[contains(attribute::class, "results")]//h6')
            ->getNode(0)
            ->textContent;

        $segmentTitles = $crawler->filterXPath('//h2[contains(attribute::class, "results")]');
        $segmentContent = $crawler->filterXPath('//div[contains(attribute::class, "resultsdiv")]');

        $this->buildShowStringFromResponse($showID, $showTitle, $showLocation, $segmentTitles, $segmentContent);

        return 0;
    }

    /**
     * @param string $title
     * @param string $location
     * @param Crawler $segmentTitles
     * @param Crawler $segmentContent
     * @return void
     */
    private function buildShowStringFromResponse(
        string $title,
        string $location,
        Crawler $segmentTitles,
        Crawler $segmentContent
    ): void {
        $showContentString = strtoupper($title) . PHP_EOL . $location . PHP_EOL;
        $showContentString .= str_repeat('-', 60) . PHP_EOL . PHP_EOL;

        for ($segmentCount = 0; $segmentCount < $segmentTitles->count(); $segmentCount++) {
            $showContentString .= strtoupper($segmentTitles->getNode($segmentCount)->textContent)
                . PHP_EOL
                . $segmentContent->getNode($segmentCount)->textContent
                . PHP_EOL . PHP_EOL . PHP_EOL;
        }

        $homepath = $_SERVER['HOME'];
        $outputBaseDirectory = $homepath . '/prime-revolution-cli';
        $filesystem = new Filesystem();
        if (! $filesystem->exists($outputBaseDirectory)) {
            $filesystem->mkdir($outputBaseDirectory);
        }

        $showDate = date('Y-m-d', strtotime(trim(explode('/', $location)[0])));

        $filesystem->appendToFile($this->temporaryOutputDirectory
            . "/{$showDate}_{$title}.txt", $showContentString);
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