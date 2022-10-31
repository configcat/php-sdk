<?php

namespace ConfigCat;

/**
 * Events fired by ConfigCatClient.
 * @package ConfigCat
 */
final class Hooks
{
    /** @var array */
    private $onConfigChanged = [];
    /** @var array */
    private $onFlagEvaluated = [];
    /** @var array */
    private $onError = [];

    /**
     * This event is sent when the SDK loads a valid config.json into memory from cache,
     * and each subsequent time when the loaded config.json changes via HTTP.
     */
    public function addOnConfigChanged(callable $callback) {
        $this->onConfigChanged[] = $callback;
    }

    /**
     * This event is sent each time when the SDK evaluates a feature flag or setting. The event sends
     * the same evaluation details that you would get from [ConfigCatClient.getValueDetails].
     */
    public function addOnFlagEvaluated(callable $callback) {
        $this->onFlagEvaluated[] = $callback;
    }

    /**
     * This event is sent when an error occurs within the SDK.
     */
    public function addOnError(callable $callback) {
        $this->onError[] = $callback;
    }

    /**
     * @internal
     */
    function fireOnConfigChanged(array $settings) {
        foreach ($this->onConfigChanged as $callback) {
            call_user_func($callback, $settings);
        }
    }

    /**
     * @internal
     */
    function fireOnFlagEvaluated(EvaluationDetails $details) {
        foreach ($this->onFlagEvaluated as $callback) {
            call_user_func($callback, $details);
        }
    }

    /**
     * @internal
     */
    function fireOnError(string $error) {
        foreach ($this->onError as $callback) {
            call_user_func($callback, $error);
        }
    }
}