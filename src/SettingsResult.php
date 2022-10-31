<?php

namespace ConfigCat;

/**
 * @internal
 */
class SettingsResult
{
    /** @var array */
    public $settings;
    /** @var int */
    public $fetchTime;

    public function __construct(array $settings, int $fetchTime)
    {
        $this->settings = $settings;
        $this->fetchTime = $fetchTime;
    }
}
