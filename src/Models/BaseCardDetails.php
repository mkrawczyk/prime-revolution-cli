<?php

declare(strict_types=1);

namespace Mkrawczyk\PrimeRevolutionCli\Models;

class BaseCardDetails
{
    /**
     * TODO: Refactor or remove this class. It's only being used for one piece of data.
     */
    public function __construct(
        private int $id
    ) {
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }
}
