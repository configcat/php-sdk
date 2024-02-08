<?php

declare(strict_types=1);

namespace ConfigCat;

/**
 * @internal
 */
final class SettingsResult
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(public array $settings, public float $fetchTime, public bool $hasConfigJson) {}
}
