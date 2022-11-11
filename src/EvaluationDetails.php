<?php

namespace ConfigCat;

class EvaluationDetails
{
    /** @var string */
    private $key;
    /** @var string|null */
    private $variationId;
    /** @var mixed */
    private $value;
    /** @var User|null */
    private $user;
    /** @var bool */
    private $isDefaultValue;
    /** @var string */
    private $error;
    /** @var int */
    private $fetchTimeUnixSeconds;
    /** @var array|null */
    private $matchedEvaluationRule;
    /** @var array|null */
    private $matchedEvaluationPercentageRule;

    /**
     * @internal
     */
    public function __construct(
        string $key,
        ?string $variationId,
        $value,
        ?User $user,
        bool $isDefaultValue,
        ?string $error,
        int $fetchTimeUnixSeconds,
        ?array $matchedEvaluationRule,
        ?array $matchedEvaluationPercentageRule
    ) {
        $this->key = $key;
        $this->variationId = $variationId;
        $this->value = $value;
        $this->user = $user;
        $this->isDefaultValue = $isDefaultValue;
        $this->error = $error;
        $this->fetchTimeUnixSeconds = $fetchTimeUnixSeconds;
        $this->matchedEvaluationRule = $matchedEvaluationRule;
        $this->matchedEvaluationPercentageRule = $matchedEvaluationPercentageRule;
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
     * @return string the variation ID (analytics)
     */
    public function getVariationId(): ?string
    {
        return $this->variationId;
    }

    /**
     * @return mixed the evaluated value of the feature flag or setting.
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return User the user object that was used for evaluation.
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
     * @return string in case of an error, the error message.
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
     * @return array the targeting rule the evaluation was based on.
     */
    public function getMatchedEvaluationRule(): ?array
    {
        return $this->matchedEvaluationRule;
    }

    /**
     * @return array the percentage rule the evaluation was based on.
     */
    public function getMatchedEvaluationPercentageRule(): ?array
    {
        return $this->matchedEvaluationPercentageRule;
    }
}
