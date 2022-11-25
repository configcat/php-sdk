<?php

namespace ConfigCat;

/**
 * @internal
 */
class EvaluationLogCollector implements \Stringable
{
    private array $entries = [];

    public function add($entry): void
    {
        $this->entries[] = $entry;
    }

    public function __toString(): string
    {
        return implode(\PHP_EOL, $this->entries);
    }
}
