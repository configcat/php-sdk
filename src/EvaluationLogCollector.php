<?php

declare(strict_types=1);

namespace ConfigCat;

/**
 * @internal
 */
class EvaluationLogCollector
{
    /**
     * @var string[]
     */
    private array $entries = [];

    public function __toString(): string
    {
        return implode(PHP_EOL, $this->entries);
    }

    public function add(string $entry): void
    {
        $this->entries[] = $entry;
    }
}
