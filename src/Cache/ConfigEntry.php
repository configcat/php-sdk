<?php

namespace ConfigCat\Cache;

use UnexpectedValueException;

/**
 * Represents the cached configuration.
 * @package ConfigCat
 */
class ConfigEntry
{
    private static ?ConfigEntry $empty = null;

    private function __construct(
        private readonly string $configJson,
        private readonly array $config,
        private readonly string $etag,
        private readonly int $fetchTime,
    ) {
    }

    public function getConfigJson(): string
    {
        return $this->configJson;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getEtag(): string
    {
        return $this->etag;
    }

    public function getFetchTime(): int
    {
        return $this->fetchTime;
    }

    public function serialize(): string
    {
        return ($this->fetchTime * 1000) . "\n" . $this->etag . "\n" . $this->configJson;
    }

    public function withTime(int $time): ConfigEntry
    {
        return new ConfigEntry($this->configJson, $this->config, $this->etag, $time);
    }

    public static function empty(): ConfigEntry
    {
        if (self::$empty == null) {
            self::$empty = new ConfigEntry("", [], "", 0);
        }

        return self::$empty;
    }

    public static function fromConfigJson(string $configJson, string $etag, int $fetchTime): ConfigEntry
    {
        $deserialized = json_decode($configJson, true);
        if ($deserialized == null) {
            return self::empty();
        }

        return new ConfigEntry($configJson, $deserialized, $etag, $fetchTime);
    }

    public static function fromCached(string $cached): ConfigEntry
    {
        $timePos = strpos($cached, "\n");
        $etagPos = strpos($cached, "\n", $timePos + 1);

        if ($timePos === false || $etagPos === false) {
            throw new UnexpectedValueException("Number of values is fewer than expected.");
        }

        $fetchTimeString = substr($cached, 0, $timePos);
        $fetchTime = intval($fetchTimeString);

        if ($fetchTime === 0) {
            throw new UnexpectedValueException("Invalid fetch time: " . $fetchTimeString);
        }

        $etag = substr($cached, $timePos + 1, $etagPos - $timePos - 1);
        $configJson = substr($cached, $etagPos + 1);

        return self::fromConfigJson($configJson, $etag, $fetchTime / 1000);
    }
}
