<?php

declare(strict_types=1);

namespace ConfigCat;

/**
 * @internal
 */
final class EvaluationResult
{
    /**
     * @param null|mixed[] $targetingRule
     * @param null|mixed[] $percentageRule
     */
    public function __construct(
        public mixed $value,
        public string $variationId,
        public ?array $targetingRule,
        public ?array $percentageRule
    ) {
    }
}
