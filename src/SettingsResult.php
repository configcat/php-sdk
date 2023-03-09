<?php

namespace ConfigCat;

/**
 * @internal
 */
class SettingsResult
{
    public function __construct(public array $settings, public int $fetchTime)
    {
    }
}
