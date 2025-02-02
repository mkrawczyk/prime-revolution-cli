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
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class FetchRevolution extends Command {

    private const QUERY_URL_BASE = 'http://prime.e-wrestling.org/content.php';

    /**
     * Has a list of all the shows in the format:
     * <table class="archive">
     *  <tr class="archiveheading">...</tr>
     *  <tr class="archiverow1 or archiverow2">
     *      <td>
     *          <a href="content.php?p=results&id=SHOW_ID"> SHOW_TITLE </a>
     *      </td>
     *      <td>
     *          SHOW_DATE
     *      </td>
     *      <td> Information we don't care about </td>
     *      <td> More information we don't care about </td>
     *  </tr>
     * </table>
     */
    private const SHOW_CONTENT_LIST = 'http://prime.e-wrestling.org/content.php?p=archives';

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
        //$this->getShowByID(188);
        $showList = $this->getShowListFromArchive();
        print_r($showList);

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

        $client = new Client();

        try {
            $response = $client->request(
                'GET',
                self::SHOW_CONTENT_LIST,
                [
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

            //echo $showID . '  ' . $showTitle . '  ' . $showDate, PHP_EOL;

            $showList[] = [
                'id' => $showID,
                'title' => $showTitle,
                'showData' => $showDate,
            ];
        }

        return $showList;
    }

    /**
     * @throws GuzzleException
     */
    private function getShowByID(int $showID): int
    {
        $client = new Client();

        try {
            $response = $client->request(
                'GET',
                self::QUERY_URL_BASE,
                [
                    'query' => [
                        'p' => 'results',
                        'id' => $showID,
                    ],
                    'headers' => [
                        'Accept' => 'text/html; charset=UTF-8',
                        'User-Agent' => 'prime-revolution-cli',
                    ],
                ]
            );

            $responseBodyAsString = $response->getBody()->getContents();
        } catch (ServerException $e) {
            // The site that we're hitting is serving pages with both content and a 500 response code, which is causing
            // us to fall into this block with alarming frequency. Because we have no control over the response, we're
            // forced to handle that here. Yes, it's jank.
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();
        }

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

        echo $this->buildShowStringFromResponse($showTitle, $showLocation, $segmentTitles, $segmentContent);

        return 0;
    }

    /**
     * @param string $title
     * @param string $location
     * @param Crawler $segmentTitles
     * @param Crawler $segmentContent
     * @return string
     */
    private function buildShowStringFromResponse(
        string $title,
        string $location,
        Crawler $segmentTitles,
        Crawler $segmentContent
    ): string {
        $showContentString = strtoupper($title) . PHP_EOL . $location . PHP_EOL;
        $showContentString .= str_repeat('-', 60) . PHP_EOL . PHP_EOL;

        for ($segmentCount = 0; $segmentCount < $segmentTitles->count(); $segmentCount++) {
            $showContentString .= strtoupper($segmentTitles->getNode($segmentCount)->textContent)
                . PHP_EOL
                . $segmentContent->getNode($segmentCount)->textContent
                . PHP_EOL . PHP_EOL . PHP_EOL;
        }

        return $showContentString;
    }
}