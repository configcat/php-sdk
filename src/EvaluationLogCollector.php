<?php

namespace ConfigCat;

class EvaluationLogCollector
{
    /** @var array */
    private $entries = [];

    public function add($entry)
    {
        $this->entries[] = $entry;
    }

    public function __toString()
    {
        return join(PHP_EOL, $this->entries);
    }
}
