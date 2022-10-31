<?php

namespace ConfigCat;

/**
 * Represents the result of forceRefresh().
 */
class RefreshResult
{
    /** @var bool */
    private $isSuccess;
    /** @var string|null */
    private $error;

    /**
     * @param bool $isSuccess
     * @param string|null $error
     * @internal
     */
    public function __construct(bool $isSuccess, ?string $error)
    {
        $this->isSuccess = $isSuccess;
        $this->error = $error;
    }

    /**
     * Returns true when the refresh was successful.
     *
     * @return bool true when the refresh was successful.
     */
    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }

    /**
     * Returns the reason if the refresh fails.
     *
     * @return string|null the reason of the failure.
     */
    public function getError(): ?string
    {
        return $this->error;
    }
}
