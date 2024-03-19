<?php

declare(strict_types=1);

namespace ConfigCat;

use Throwable;

class EvaluationDetails
{
    /**
     * @param ?array<string, mixed> $matchedTargetingRule
     * @param ?array<string, mixed> $matchedPercentageOption
     *
     * @internal
     */
    public function __construct(
        private readonly string $key,
        private readonly ?string $variationId,
        private readonly mixed $value,
        private readonly ?User $user,
        private readonly bool $isDefaultValue,
        private readonly ?string $errorMessage,
        private readonly ?Throwable $errorException,
        private readonly float $fetchTimeUnixMilliseconds,
        private readonly ?array $matchedTargetingRule,
        private readonly ?array $matchedPercentageOption
    ) {}

    /**
     * @internal
     */
    public static function fromError(string $key, mixed $value, ?User $user, string $errorMessage, ?Throwable $errorException = null): EvaluationDetails
    {
        return new EvaluationDetails(
            $key,
            null,
            $value,
            $user,
            true,
            $errorMessage,
            $errorException,
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
     * @return ?string error message in case evaluation failed
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @return ?Throwable the `Throwable` object related to the error in case evaluation failed (if any)
     */
    public function getErrorException(): ?Throwable
    {
        return $this->errorException;
    }

    /**
     * @return float the last download time of the current config in unix milliseconds format
     */
    public function getFetchTimeUnixMilliseconds(): float
    {
        return $this->fetchTimeUnixMilliseconds;
    }

    /**
     * @return ?array<string, mixed> the targeting rule (if any) that matched during the evaluation and was used to return the evaluated value
     */
    public function getMatchedTargetingRule(): ?array
    {
        return $this->matchedTargetingRule;
    }

    /**
     * @return ?array<string, mixed> the percentage option (if any) that was used to select the evaluated value
     */
    public function getMatchedPercentageOption(): ?array
    {
        return $this->matchedPercentageOption;
    }
}
