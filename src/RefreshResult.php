<?php

declare(strict_types=1);

namespace ConfigCat;

use Throwable;

/**
 * Represents the result of forceRefresh().
 */
class RefreshResult
{
    /**
     * @internal
     */
    public function __construct(private readonly ?string $errorMessage = null, private readonly ?Throwable $errorException = null) {}

    /**
     * Indicates whether the operation was successful or not.
     *
     * @return bool `true` when the refresh was successful
     */
    public function isSuccess(): bool
    {
        return !isset($this->errorMessage);
    }

    /**
     * Returns the error message in case the operation failed, otherwise `null`.
     *
     * @return ?string the reason of the failure
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * Returns the `Throwable` object related to the error in case the operation failed (if any).
     *
     * @return ?Throwable the `Throwable` object related to the error
     */
    public function getErrorException(): ?Throwable
    {
        return $this->errorException;
    }
}
