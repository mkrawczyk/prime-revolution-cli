<?php

declare(strict_types=1);

namespace Mkrawczyk\PrimeRevolutionCli\Services;

use DOMNode;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Mkrawczyk\PrimeRevolutionCli\Models\ArchiveItem;
use Mkrawczyk\PrimeRevolutionCli\Models\BaseCardDetails;
use Mkrawczyk\PrimeRevolutionCli\Models\PrimeCard;
use Symfony\Component\DomCrawler\Crawler;

class ContentService {

    /**
     * Base URL for the old PRIME (2007-ish through 2012) website.
     */
    private const QUERY_URL_BASE = 'http://prime.e-wrestling.org/content.php';

    /**
     * The markup on the show archives page that we're scraping is very much a product of the mid-2000s before
     * semantic markup became a thing, so it leans on tables for its formatting.
     */
    private const ARCHIVE_XPATH_BASE = '(//table[contains(attribute::class, "archive")]//'
        . 'tr[contains(attribute::class, "archiverow1") or contains(attribute::class, "archiverow2")])';

    public function __construct(
        private Client $client,
        private Crawler $crawler
    ) {}

    /**
     * @return BaseCardDetails[]|false
     *
     * @throws GuzzleException
     */
    public function getShowListFromArchive(): array|false
    {
        $showList = [];

        $responseBodyAsString = $this->getSiteContent(
            $this->client,
            [
                'p' => 'archives',
            ]
        );

        if (empty($responseBodyAsString)) {
            return false;
        }

        $this->crawler->addHtmlContent($responseBodyAsString, 'UTF-8');
        $archiveCollection = $this->crawler->filterXPath(self::ARCHIVE_XPATH_BASE)->each(
            function (Crawler $node): ArchiveItem {
                return new ArchiveItem(
                    $node->filterXPath('//td[1]//a/@href')->getNode(0)->textContent ?? '',
                    $node->filterXPath('//td[1]/a')->getNode(0)->textContent ?? '',
                    $node->filterXPath('//td[2]')->getNode(0)->textContent ?? ''
                );
            }
        );

        // This block makes the assumption that we will always have the same number of URLs,
        // titles, and dates. We're doing this both because of how the Backstage Script the original
        // PRIME was built on operates, and because the markup for that site is... well, iffy.
        foreach ($archiveCollection as $archiveItem) {
            if (! $archiveItem instanceof ArchiveItem) {
                continue;
            }

            if (empty($archiveItem->getUrl())) {
                continue;
            }

            $urlFragments = parse_url($archiveItem->getUrl());
            if (empty($urlFragments['query'])) {
                continue;
            }
            parse_str($urlFragments['query'], $query);

            if (empty($query['id'])) {
                continue;
            }

            $showList[] = new BaseCardDetails(intval($query['id']), $archiveItem->getTitle(), $archiveItem->getDate());
        }

        $this->crawler->clear();

        return $showList;
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

        $this->crawler->clear();

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