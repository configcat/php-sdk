<?php

namespace ConfigCat;

/**
 * @internal
 */
class EvaluationLogCollector
{
    /** @var array */
    private $entries = [];

    public function add($entry): void
    {
        $this->entries[] = $entry;
    }

    public function __toString(): string
    {
        return join(PHP_EOL, $this->entries);
    }
}
