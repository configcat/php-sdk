<?php

namespace ConfigCat;

/**
 * @internal
 */
final class EvaluationResult
{
    public $value;
    public $variationId;
    public $targetingRule;
    public $percentageRule;

    public function __construct($value, string $variationId, ?array $targetingRule, ?array $percentageRule)
    {
        $this->value = $value;
        $this->variationId = $variationId;
        $this->targetingRule = $targetingRule;
        $this->percentageRule = $percentageRule;
    }
}