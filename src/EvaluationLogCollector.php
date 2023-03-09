<?php

namespace ConfigCat;

/**
 * @internal
 */
class EvaluationLogCollector
{
    private array $entries = [];

    public function add(string $entry): void
    {
        $this->entries[] = $entry;
    }

    public function __toString(): string
    {
        return implode(PHP_EOL, $this->entries);
    }
}
