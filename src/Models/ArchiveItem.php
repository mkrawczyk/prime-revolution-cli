<?php

declare(strict_types=1);

namespace Mkrawczyk\PrimeRevolutionCli\Models;

class ArchiveItem
{
    public function __construct(
        private string $url,
        private string $title,
        private string $date
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDate(): string
    {
        return $this->date;
    }
}
