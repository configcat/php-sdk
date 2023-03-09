<?php

namespace ConfigCat;

class EvaluationDetails
{
    /**
     * @internal
     */
    public function __construct(
        private readonly string $key,
        private readonly ?string $variationId,
        private readonly mixed $value,
        private readonly ?User $user,
        private readonly bool $isDefaultValue,
        private readonly ?string $error,
        private readonly int $fetchTimeUnixSeconds,
        private readonly ?array $matchedEvaluationRule,
        private readonly ?array $matchedEvaluationPercentageRule
    ) {
    }

    /**
     * @internal
     */
    public static function fromError(string $key, $value, ?User $user, string $error): EvaluationDetails
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
     * @return string the key of the evaluated feature flag or setting.
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
     * @return mixed the evaluated value of the feature flag or setting.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * @return ?User the user object that was used for evaluation.
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @return bool true when the default value passed to getValueDetails() is returned due to an error.
     */
    public function isDefaultValue(): bool
    {
        return $this->isDefaultValue;
    }

    /**
     * @return ?string in case of an error, the error message.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @return int the last download time of the current config in unix seconds format.
     */
    public function getFetchTimeUnixSeconds(): int
    {
        return $this->fetchTimeUnixSeconds;
    }

    /**
     * @return ?array the targeting rule the evaluation was based on.
     */
    public function getMatchedEvaluationRule(): ?array
    {
        return $this->matchedEvaluationRule;
    }

    /**
     * @return ?array the percentage rule the evaluation was based on.
     */
    public function getMatchedEvaluationPercentageRule(): ?array
    {
        return $this->matchedEvaluationPercentageRule;
    }
}
