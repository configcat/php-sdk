<?php

namespace ConfigCat;

use Psr\Log\LoggerInterface;
use z4kn4fein\SemVer\Version;
use z4kn4fein\SemVer\VersionFormatException;

/**
 * Class RolloutEvaluator
 * @package ConfigCat
 */
final class RolloutEvaluator
{
    /** @var LoggerInterface */
    private $logger;

    private $comparatorTexts = [
        "IS ONE OF",
        "IS NOT ONE OF",
        "CONTAINS",
        "DOES NOT CONTAIN",
        "IS ONE OF (SemVer)",
        "IS NOT ONE OF (SemVer)",
        "< (SemVer)",
        "<= (SemVer)",
        "> (SemVer)",
        ">= (SemVer)",
        "= (Number)",
        "<> (Number)",
        "< (Number)",
        "<= (Number)",
        "> (Number)",
        ">= (Number)",
        "IS ONE OF (Sensitive)",
        "IS NOT ONE OF (Sensitive)"
    ];

    /**
     * RolloutEvaluator constructor.
     *
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Evaluates a requested value from the configuration by the specified roll out rules.
     *
     * @param string $key The key of the desired value.
     * @param array $json The decoded JSON configuration.
     * @param User|null $user Optional. The user to identify the caller.
     * @return mixed The evaluated configuration value.
     */
    public function evaluate($key, array $json, User $user = null)
    {
        $this->logger->info("Evaluating getValue(" . $key . ").");

        if (is_null($user)) {
            if (isset($json['r']) && !empty($json['r']) || isset($json['p']) && !empty($json['p'])) {
                $this->logger->warning("UserObject missing! You should pass a " .
                    "UserObject to getValue() in order to make targeting work properly. " .
                    "Read more: https://configcat.com/docs/advanced/user-object.");
            }

            $result = $json['v'];
            $this->logger->info("Returning ". $result .".");
            return $result;
        }

        $this->logger->info("User object: " . $user);

        if (isset($json['r']) && !empty($json['r'])) {
            foreach ($json['r'] as $rule) {
                $comparisonAttribute = $rule['a'];
                $comparisonValue = $rule['c'];
                $comparator = $rule['t'];
                $value = $rule['v'];
                $userValue = $user->getAttribute($comparisonAttribute);

                if (empty($comparisonValue) || (!is_numeric($userValue) && empty($userValue))) {
                    $this->logNoMatch($comparisonAttribute, $userValue, $comparator, $comparisonValue);
                    continue;
                }

                switch ($comparator) {
                    //IS ONE OF
                    case 0:
                        $split = array_filter(Utils::splitTrim($comparisonValue));
                        if (in_array($userValue, $split, true)) {
                            $this->logMatch(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                $comparisonValue,
                                $value
                            );
                            return $value;
                        }
                        break;
                    //IS NOT ONE OF
                    case 1:
                        $split = array_filter(Utils::splitTrim($comparisonValue));
                        if (!in_array($userValue, $split, true)) {
                            $this->logMatch(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                $comparisonValue,
                                $value
                            );
                            return $value;
                        }
                        break;
                    //CONTAINS
                    case 2:
                        if (Utils::strContains($userValue, $comparisonValue)) {
                            $this->logMatch(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                $comparisonValue,
                                $value
                            );
                            return $value;
                        }
                        break;
                    //DOES NOT CONTAIN
                    case 3:
                        if (!Utils::strContains($userValue, $comparisonValue)) {
                            $this->logMatch(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                $comparisonValue,
                                $value
                            );
                            return $value;
                        }
                        break;
                    //IS ONE OF, IS NOT ONE OF (SemVer)
                    case 4:
                    case 5:
                        $split = array_filter(Utils::splitTrim($comparisonValue));
                        try {
                            $matched = false;
                            foreach ($split as $semVer) {
                                $matched = Version::equal($userValue, $semVer) || $matched;
                            }

                            if (($matched && $comparator == 4) || (!$matched && $comparator == 5)) {
                                $this->logMatch(
                                    $comparisonAttribute,
                                    $userValue,
                                    $comparator,
                                    $comparisonValue,
                                    $value
                                );
                                return $value;
                            }
                        } catch (VersionFormatException $exception) {
                            $this->logMatch(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                $comparisonValue,
                                $value
                            );
                            break;
                        }

                        break;
                    //LESS THAN, LESS THAN OR EQUALS TO, GREATER THAN, GREATER THAN OR EQUALS TO (SemVer)
                    case 6:
                    case 7:
                    case 8:
                    case 9:
                        try {
                            if (($comparator == 6 &&
                                    Version::lessThan($userValue, $comparisonValue)) ||
                                ($comparator == 7 &&
                                    Version::lessThanOrEqual($userValue, $comparisonValue)) ||
                                ($comparator == 8 &&
                                    Version::greaterThan($userValue, $comparisonValue)) ||
                                ($comparator == 9 &&
                                    Version::greaterThanOrEqual($userValue, $comparisonValue))) {
                                $this->logMatch(
                                    $comparisonAttribute,
                                    $userValue,
                                    $comparator,
                                    $comparisonValue,
                                    $value
                                );
                                return $value;
                            }
                        } catch (VersionFormatException $exception) {
                            $this->logFormatError(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                $comparisonValue,
                                $exception
                            );
                            break;
                        }
                        break;
                    //LESS THAN, LESS THAN OR EQUALS TO, GREATER THAN, GREATER THAN OR EQUALS TO (Number)
                    case 10:
                    case 11:
                    case 12:
                    case 13:
                    case 14:
                    case 15:
                        $userDouble = str_replace(",", ".", $userValue);
                        $comparisonDouble = str_replace(",", ".", $comparisonValue);
                        if (!is_numeric($userDouble)) {
                            $this->logFormatErrorWithMessage(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                $comparisonValue,
                                $userDouble . "is not a valid number."
                            );
                            break;
                        }

                        if (!is_numeric($comparisonDouble)) {
                            $this->logFormatErrorWithMessage(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                $comparisonValue,
                                $comparisonDouble . "is not a valid number."
                            );
                            break;
                        }

                        $userDoubleValue = floatval($userDouble);
                        $comparisonDoubleValue = floatval($comparisonDouble);

                        if (($comparator == 10 && $userDoubleValue == $comparisonDoubleValue) ||
                            ($comparator == 11 && $userDoubleValue != $comparisonDoubleValue) ||
                            ($comparator == 12 && $userDoubleValue < $comparisonDoubleValue) ||
                            ($comparator == 13 && $userDoubleValue <= $comparisonDoubleValue) ||
                            ($comparator == 14 && $userDoubleValue > $comparisonDoubleValue) ||
                            ($comparator == 15 && $userDoubleValue >= $comparisonDoubleValue)) {
                            $this->logMatch($comparisonAttribute, $userValue, $comparator, $comparisonValue, $value);
                            return $value;
                        }
                        break;
                    //IS ONE OF (Sensitive)
                    case 16:
                        $split = array_filter(Utils::splitTrim($comparisonValue));
                        if (in_array(sha1($userValue), $split, true)) {
                            $this->logMatch(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                $comparisonValue,
                                $value
                            );
                            return $value;
                        }
                        break;
                    //IS NOT ONE OF (Sensitive)
                    case 17:
                        $split = array_filter(Utils::splitTrim($comparisonValue));
                        if (!in_array(sha1($userValue), $split, true)) {
                            $this->logMatch(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                $comparisonValue,
                                $value
                            );
                            return $value;
                        }
                        break;
                }
                $this->logNoMatch($comparisonAttribute, $userValue, $comparator, $comparisonValue);
            }
        }

        if (isset($json['p']) && !empty($json['p'])) {
            $hashCandidate = $key . $user->getIdentifier();
            $stringHash = substr(sha1($hashCandidate), 0, 7);
            $intHash = intval($stringHash, 16);
            $scale = $intHash % 100;

            $bucket = 0;
            foreach ($json['p'] as $rule) {
                $bucket += $rule['p'];
                if ($scale < $bucket) {
                    $result = $rule['v'];
                    $this->logger->info("Evaluating % options. Returning " . $result . ".");
                    return $result;
                }
            }
        }

        $result = $json['v'];
        $this->logger->info("Returning ". $result .".");
        return $result;
    }

    private function logMatch($comparisonAttribute, $userValue, $comparator, $comparisonValue, $value)
    {
        $this->logger->info("Evaluating rule: [". $comparisonAttribute . ":" . $userValue . "] " .
        "[" . $this->comparatorTexts[$comparator] . "] " .
        "[" . $comparisonValue . "] => match, returning: " . $value. "");
    }

    private function logNoMatch($comparisonAttribute, $userValue, $comparator, $comparisonValue)
    {
        $this->logger->info("Evaluating rule: [". $comparisonAttribute . ":" . $userValue . "] " .
            "[" . $this->comparatorTexts[$comparator] . "] " .
            "[" . $comparisonValue . "] => no match");
    }

    private function logFormatError(
        $comparisonAttribute,
        $userValue,
        $comparator,
        $comparisonValue,
        \Exception $exception
    ) {
        $this->logger->warning(
            "Evaluating rule: [". $comparisonAttribute . ":" . $userValue . "] " .
            "[" . $this->comparatorTexts[$comparator] . "] " .
            "[" . $comparisonValue . "] => SKIP rule. Validation error: " . $exception->getMessage() . "",
            ['exception' => $exception]
        );
    }

    private function logFormatErrorWithMessage(
        $comparisonAttribute,
        $userValue,
        $comparator,
        $comparisonValue,
        $message
    ) {
        $this->logger->warning("Evaluating rule: [". $comparisonAttribute . ":" . $userValue . "] " .
        "[" . $this->comparatorTexts[$comparator] . "] " .
        "[" . $comparisonValue . "] => SKIP rule. Validation error: " . $message . "");
    }
}
