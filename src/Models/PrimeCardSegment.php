<?php

namespace Mkrawczyk\PrimeRevolutionCli\Models;

use Symfony\Component\DomCrawler\Crawler;

class PrimeCardSegment {

    public function __construct(
        private ?string $title = null,
        private ?string $content = null
    ) {
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title ?? '';
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content ?? '';
    }

    /**
     * @param string $content
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function buildFromCrawlerByOffset(Crawler $listTitles, Crawler $listContent, int $offset): void
    {
        $this->title = $listTitles->getNode($offset)->textContent;
        $this->content = $listContent->getNode($offset)->textContent;
    }

    /**
     * Returns the model as a string that will be appended to the larger show string we're building.
     */
    public function getAsStringForPrimeCard(): string
    {
        return strtoupper($this->title) . PHP_EOL . $this->content . PHP_EOL . PHP_EOL;
    }
}