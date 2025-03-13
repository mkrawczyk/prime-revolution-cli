<?php

declare(strict_types=1);

namespace Mkrawczyk\PrimeRevolutionCli\Models;

use DOMNode;
use Symfony\Component\DomCrawler\Crawler;

class PrimeCardSegment
{
    public function __construct(
        private ?string $title = null,
        private ?string $content = null
    ) {
    }

    /**
     * Sets the title and content block based on the offset provided. There is likely a cleaner solution for this
     * but given the way the page's markup is structured we (I) opted for a quick and dirty approach based on
     * the offset of where the segment appears within the show as a whole.
     */
    public function buildFromCrawlerByOffset(Crawler $listTitles, Crawler $listContent, int $offset): void
    {
        $this->title = $listTitles->getNode($offset) instanceof DOMNode
            ? $listTitles->getNode($offset)->textContent
            : '';

        $this->content = $listContent->getNode($offset) instanceof DOMNode
            ? $listContent->getNode($offset)->textContent
            : '';
    }

    /**
     * Returns the model as a string that will be appended to the larger show string we're building.
     */
    public function getAsStringForPrimeCard(): string
    {
        // Exit early if the segment has no content. This *shouldn't* happen.
        if (! is_string($this->content) || empty($this->content)) {
            return '';
        }

        if (! is_string($this->title)) {
            $this->title = 'untitled';
        }

        return strtoupper($this->title) . PHP_EOL . $this->content . PHP_EOL . PHP_EOL;
    }
}
