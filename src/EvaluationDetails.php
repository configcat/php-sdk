<?php

declare(strict_types=1);

namespace ConfigCat;

class EvaluationDetails
{
    /**
     * @param null|mixed[] $matchedEvaluationRule
     * @param null|mixed[] $matchedEvaluationPercentageRule
     *
     * @internal
     */
    public function __construct(
        private readonly string $key,
        private readonly ?string $variationId,
        private readonly mixed $value,
        private readonly ?User $user,
        private readonly bool $isDefaultValue,
        private readonly ?string $error,
        private readonly float $fetchTimeUnixMilliseconds,
        private readonly ?array $matchedEvaluationRule,
        private readonly ?array $matchedEvaluationPercentageRule
    ) {}

    /**
     * @internal
     */
    public static function fromError(string $key, mixed $value, ?User $user, ?string $error): EvaluationDetails
    {
        return new EvaluationDetails(
            $key,
            null,
            $value,
            $user,
            true,
            $error,
            0,
            null,
            null
        );
    }

    /**
     * @return string the key of the evaluated feature flag or setting
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return ?string the variation ID (analytics)
     */
    public function getVariationId(): ?string
    {
        return $this->variationId;
    }

    /**
     * @return mixed the evaluated value of the feature flag or setting
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * @return ?User the user object that was used for evaluation
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @return bool true when the default value passed to getValueDetails() is returned due to an error
     */
    public function isDefaultValue(): bool
    {
        return $this->isDefaultValue;
    }

    /**
     * @return ?string in case of an error, the error message
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @return float the last download time of the current config in unix milliseconds format
     */
    public function getFetchTimeUnixMilliseconds(): float
    {
        return $this->fetchTimeUnixMilliseconds;
    }

    /**
     * @return null|array<string, mixed> the targeting rule the evaluation was based on
     */
    public function getMatchedEvaluationRule(): ?array
    {
        return $this->matchedEvaluationRule;
    }

    /**
     * @return null|array<string, mixed> the percentage rule the evaluation was based on
     */
    public function getMatchedEvaluationPercentageRule(): ?array
    {
        return $this->matchedEvaluationPercentageRule;
    }
}
