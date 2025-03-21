<?php

declare(strict_types=1);

namespace Mkrawczyk\PrimeRevolutionCli\Models;

use DOMNode;
use Symfony\Component\DomCrawler\Crawler;

class PrimeCard
{
    /**
     * Xpath query for where the name of the show/card is held in the markup.
     */
    private const string CARD_TITLE_XPATH = '//div[contains(attribute::class, "results")]//h1';

    /**
     * Xpath query for the where this fictitious event is being held.
     */
    private const string CARD_LOCATION_XPATH = '//div[contains(attribute::class, "results")]//h6';

    /**
     * Query for how the segment titles are structured.
     */
    private const string CARD_SEGMENT_TITLES_XPATH = '//h2[contains(attribute::class, "results")]';

    /**
     * Query for fetching individual segment content.
     */
    private const string CARD_SEGMENT_CONTENT_XPATH = '//div[contains(attribute::class, "resultsdiv")]';

    private string $title;
    private string $location;
    /**
     * @var PrimeCardSegment[]
     */
    private array $segments;

    public function __construct(
        private Crawler $crawler
    ) {
    }

    private function getNodeContent(Crawler $crawler, string $defaultText = ''): string
    {
        return $crawler->getNode(0) instanceof DOMNode
            ? $crawler->getNode(0)->textContent
            : $defaultText;
    }

    public function buildCardFromCrawler(): void
    {
        $this->title = $this->getNodeContent(
            $this->crawler->filterXPath(self::CARD_TITLE_XPATH),
            'title unknown'
        );

        $this->location = $this->getNodeContent(
            $this->crawler->filterXPath(self::CARD_LOCATION_XPATH),
            'location undefined'
        );

        $segmentTitles = $this->crawler->filterXPath(self::CARD_SEGMENT_TITLES_XPATH);
        $segmentContent = $this->crawler->filterXPath(self::CARD_SEGMENT_CONTENT_XPATH);

        for ($offset = 0; $offset < $segmentTitles->count(); $offset++) {
            $segment = new PrimeCardSegment();
            $segment->buildFromCrawlerByOffset(
                $segmentTitles,
                $segmentContent,
                $offset
            );

            $this->segments[] = $segment;
        }
    }

    public function getCardAsString(): string
    {
        $contentString = strtoupper($this->title) . PHP_EOL . $this->location . PHP_EOL;
        $contentString .= str_repeat('-', 60) . PHP_EOL . PHP_EOL;

        foreach ($this->segments as $segment) {
            $contentString .= $segment->getAsStringForPrimeCard();
        }

        return $contentString;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function getDateFromLocationString(): string
    {
        $showDate = strtotime(trim(explode('/', $this->location)[0]));
        if ($showDate === false) {
            return 'unknown';
        }

        return date('Y-m-d', $showDate);
    }
}
