<?php

declare(strict_types=1);

namespace ConfigCat;

/**
 * Represents the result of forceRefresh().
 */
class RefreshResult
{
    /**
     * @internal
     */
    public function __construct(private readonly bool $isSuccess, private readonly ?string $error)
    {
    }

    /**
     * Returns true when the refresh was successful.
     *
     * @return bool true when the refresh was successful
     */
    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }

    /**
     * Returns the reason if the refresh fails.
     *
     * @return null|string the reason of the failure
     */
    public function getError(): ?string
    {
        return $this->error;
    }
}
