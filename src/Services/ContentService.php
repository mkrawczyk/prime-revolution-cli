<?php

namespace Mkrawczyk\PrimeRevolutionCli\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
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
     * @return list<array{id: int, title: string, showDate: string}>
     *
     * @throws GuzzleException
     */
    public function getShowListFromArchive(): array
    {
        $showList = [];

        $queryParams= [
            'p' => 'archives',
        ];
        $responseBodyAsString = $this->getSiteContent($this->client, $queryParams);

        $this->crawler->addHtmlContent($responseBodyAsString, 'UTF-8');

        $showUrlList = $this->crawler->filterXPath(self::XPATH_QUERY_BASE . '//td[1]//a/@href');
        $showTitleList = $this->crawler->filterXPath(self::XPATH_QUERY_BASE . '//td[1]/a');
        $showDateList = $this->crawler->filterXPath(self::XPATH_QUERY_BASE . '//td[2]');

        for ($i = 0; $i < $showUrlList->count(); $i++) {
            $showUrl = $showUrlList->getNode($i)->textContent;
            $showTitle = $showTitleList->getNode($i)->textContent;
            $showDate = $showDateList->getNode($i)->textContent;

            $urlFragments = explode('=', $showUrl);
            $showID = intval($urlFragments[count($urlFragments) - 1]);

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
    public function getPrimeCardByID(int $showID): PrimeCard
    {
        $queryParams = [
            'p' => 'results',
            'id' => $showID,
        ];
        $responseBodyAsString = $this->getSiteContent($this->client, $queryParams);

        // Needed because the original Backstage script was the wild west of allowing stuff into the DB.
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