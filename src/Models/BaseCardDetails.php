<?php

declare(strict_types=1);

namespace Mkrawczyk\PrimeRevolutionCli\Models;

class BaseCardDetails
{
    public function __construct(
        private int $id,
        private string $title,
        private string $date
    ) {
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getDate(): string
    {
        return $this->date;
    }
}
