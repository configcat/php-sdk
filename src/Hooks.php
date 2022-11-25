<?php

namespace ConfigCat;

/**
 * Events fired by ConfigCatClient.
 */
final class Hooks
{
    private array $onConfigChanged = [];
    private array $onFlagEvaluated = [];
    private array $onError = [];

    /**
     * This event is sent when the SDK loads a valid config.json into memory from cache,
     * and each subsequent time when the loaded config.json changes via HTTP.
     */
    public function addOnConfigChanged(callable $callback): void
    {
        $this->onConfigChanged[] = $callback;
    }

    /**
     * This event is sent each time when the SDK evaluates a feature flag or setting. The event sends
     * the same evaluation details that you would get from [ConfigCatClient.getValueDetails].
     */
    public function addOnFlagEvaluated(callable $callback): void
    {
        $this->onFlagEvaluated[] = $callback;
    }

    /**
     * This event is sent when an error occurs within the SDK.
     */
    public function addOnError(callable $callback): void
    {
        $this->onError[] = $callback;
    }

    /**
     * @internal
     */
    public function fireOnConfigChanged(array $settings): void
    {
        foreach ($this->onConfigChanged as $callback) {
            \call_user_func($callback, $settings);
        }
    }

    /**
     * @internal
     */
    public function fireOnFlagEvaluated(EvaluationDetails $details): void
    {
        foreach ($this->onFlagEvaluated as $callback) {
            \call_user_func($callback, $details);
        }
    }

    /**
     * @internal
     */
    public function fireOnError(string $error): void
    {
        foreach ($this->onError as $callback) {
            \call_user_func($callback, $error);
        }
    }
}
