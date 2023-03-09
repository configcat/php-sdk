<?php

namespace ConfigCat;

/**
 * @internal
 */
final class EvaluationResult
{
    public function __construct(
        public mixed $value,
        public string $variationId,
        public ?array $targetingRule,
        public ?array $percentageRule
    ) {
    }
}
