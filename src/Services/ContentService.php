<?php

declare(strict_types=1);

namespace Mkrawczyk\PrimeRevolutionCli\Services;

use DOMNode;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Mkrawczyk\PrimeRevolutionCli\Models\BaseCardDetails;
use Mkrawczyk\PrimeRevolutionCli\Models\PrimeCard;
use Symfony\Component\DomCrawler\Crawler;

class ContentService {

    private const QUERY_URL_BASE = 'http://prime.e-wrestling.org/content.php';

    // This is an ugly XPath string, but the markup for this page is very much a product of the mid-2000s.
    private const XPATH_QUERY_BASE = '(//table[contains(attribute::class, "archive")]//'
        . 'tr[contains(attribute::class, "archiverow1") or contains(attribute::class, "archiverow2")])';

    public function __construct(
        private Client $client,
        private Crawler $crawler
    ) {}

    /**
     * @return BaseCardDetails[]
     *
     * @throws GuzzleException
     */
    public function getShowListFromArchive(): array
    {
        $showList = [];

        $responseBodyAsString = $this->getSiteContent(
            $this->client,
            [
                'p' => 'archives',
            ]
        );

        $this->crawler->addHtmlContent($responseBodyAsString, 'UTF-8');

        $showUrlList = $this->crawler->filterXPath(self::XPATH_QUERY_BASE . '//td[1]//a/@href');
        $showTitleList = $this->crawler->filterXPath(self::XPATH_QUERY_BASE . '//td[1]/a');
        $showDateList = $this->crawler->filterXPath(self::XPATH_QUERY_BASE . '//td[2]');

        // This block makes the assumption that we will always have the same number of URLs,
        // titles, and dates. We're doing this both because of how the Backstage Script the original
        // PRIME was built on operates, and because the markup for that site is... well, iffy.
        for ($index = 0; $index < $showUrlList->count(); $index++) {
            $showUrl = $this->getNodeContentByIndex($showUrlList, $index);
            $showTitle = $this->getNodeContentByIndex($showTitleList, $index);
            $showDate = $this->getNodeContentByIndex($showDateList, $index);

            $urlFragments = explode('=', $showUrl);
            $showID = intval($urlFragments[count($urlFragments) - 1]);

            $showList[] = new BaseCardDetails($showID, $showTitle, $showDate);
        }

        return $showList;
    }

    private function getNodeContentByIndex(Crawler $node, int $index): string
    {
        return $node->getNode($index) instanceof DOMNode
            ? $node->getNode($index)->textContent
            : '';
    }

    /**
     * @throws GuzzleException
     */
    public function getPrimeCardByID(int $showID): PrimeCard
    {
        $responseBodyAsString = $this->getSiteContent(
            $this->client,
            [
                'p' => 'results',
                'id' => $showID,
            ]
        );

        $this->crawler->addHtmlContent($responseBodyAsString);

        $primeCard = new PrimeCard($this->crawler);
        $primeCard->buildCardFromCrawler();

        return $primeCard;
    }

    /**
     * @param array{p: string, id?: int} $queryParams
     *
     * @throws GuzzleException
     */
    private function getSiteContent(Client $client, array $queryParams): string
    {
        try {
            $response = $client->request(
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
        } catch (ServerException $serverException) {
            $response = $serverException->getResponse();
        }

        return $response->getBody()->getContents();
    }
}