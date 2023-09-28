<?php

declare(strict_types=1);

namespace ConfigCat\Cache;

use UnexpectedValueException;

/**
 * Represents the cached configuration.
 */
class ConfigEntry
{
    private static ?ConfigEntry $empty = null;

    /**
     * @param string  $configJson the config JSON
     * @param mixed[] $config     the deserialized config
     * @param string  $etag       the ETag related to the current config
     * @param float   $fetchTime  the time when the current config was fetched
     */
    private function __construct(
        private readonly string $configJson,
        private readonly array $config,
        private readonly string $etag,
        private readonly float $fetchTime,
    ) {}

    /**
     * @return string the config JSON
     */
    public function getConfigJson(): string
    {
        return $this->configJson;
    }

    /**
     * @return mixed[] the deserialized config
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return string the ETag related to the current config
     */
    public function getEtag(): string
    {
        return $this->etag;
    }

    /**
     * @return float the time when the current config was fetched
     */
    public function getFetchTime(): float
    {
        return $this->fetchTime;
    }

    public function serialize(): string
    {
        return $this->fetchTime."\n".$this->etag."\n".$this->configJson;
    }

    public function withTime(float $time): ConfigEntry
    {
        return new ConfigEntry($this->configJson, $this->config, $this->etag, $time);
    }

    public static function empty(): ConfigEntry
    {
        if (null == self::$empty) {
            self::$empty = new ConfigEntry('', [], '', 0);
        }

        return self::$empty;
    }

    public static function fromConfigJson(string $configJson, string $etag, float $fetchTime): ConfigEntry
    {
        $deserialized = json_decode($configJson, true);
        if (null == $deserialized) {
            return self::empty();
        }

        return new ConfigEntry($configJson, $deserialized, $etag, $fetchTime);
    }

    public static function fromCached(string $cached): ConfigEntry
    {
        $timePos = strpos($cached, "\n");
        $etagPos = strpos($cached, "\n", $timePos + 1);

        if (false === $timePos || false === $etagPos) {
            throw new UnexpectedValueException('Number of values is fewer than expected.');
        }

        $fetchTimeString = substr($cached, 0, $timePos);
        $fetchTime = floatval($fetchTimeString);

        if (0 == $fetchTime) {
            throw new UnexpectedValueException('Invalid fetch time: '.$fetchTimeString);
        }

        $etag = substr($cached, $timePos + 1, $etagPos - $timePos - 1);
        $configJson = substr($cached, $etagPos + 1);

        return self::fromConfigJson($configJson, $etag, $fetchTime);
    }
}
