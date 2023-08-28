<?php

namespace ConfigCat;

/**
 * @internal
 */
class SettingsResult
{
    public function __construct(public ?array $settings, public float $fetchTime)
    {
    }
}
