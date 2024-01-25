<?php

declare(strict_types=1);

namespace ConfigCat;

use ConfigCat\ConfigJson\ConditionContainer;
use ConfigCat\ConfigJson\PercentageOption;
use ConfigCat\ConfigJson\Setting;
use ConfigCat\ConfigJson\SettingValue;
use ConfigCat\ConfigJson\SettingValueContainer;
use ConfigCat\ConfigJson\TargetingRule;
use ConfigCat\ConfigJson\UserCondition;
use ConfigCat\Log\InternalLogger;
use Exception;
use z4kn4fein\SemVer\SemverException;
use z4kn4fein\SemVer\Version;

/**
 * Class RolloutEvaluator.
 *
 * @internal
 */
final class RolloutEvaluator
{
    /**
     * @var string[]
     */
    private array $comparatorTexts = [
        'IS ONE OF',
        'IS NOT ONE OF',
        'CONTAINS',
        'DOES NOT CONTAIN',
        'IS ONE OF (SemVer)',
        'IS NOT ONE OF (SemVer)',
        '< (SemVer)',
        '<= (SemVer)',
        '> (SemVer)',
        '>= (SemVer)',
        '= (Number)',
        '<> (Number)',
        '< (Number)',
        '<= (Number)',
        '> (Number)',
        '>= (Number)',
        'IS ONE OF (Sensitive)',
        'IS NOT ONE OF (Sensitive)',
    ];

    /**
     * RolloutEvaluator constructor.
     *
     * @param InternalLogger $logger the logger instance
     */
    public function __construct(private readonly InternalLogger $logger) {}

    /**
     * Evaluates a requested value from the configuration by the specified roll-out rules.
     *
     * @param string                 $key          the key of the desired value
     * @param array<string, mixed>   $json         the decoded JSON configuration
     * @param EvaluationLogCollector $logCollector the evaluation log collector
     * @param ?User                  $user         Optional. The user to identify the caller.
     *
     * @return EvaluationResult the evaluation result
     */
    public function evaluate(
        string $key,
        array $json,
        EvaluationLogCollector $logCollector,
        ?User $user = null
    ): EvaluationResult {
        $settingType = Setting::getType($json);

        if (null === $user) {
            if (isset($json[Setting::TARGETING_RULES])
                && !empty($json[Setting::TARGETING_RULES])
                || isset($json[Setting::PERCENTAGE_OPTIONS])
                && !empty($json[Setting::PERCENTAGE_OPTIONS])) {
                $this->logger->warning("Cannot evaluate targeting rules and % options for setting '".$key."' (User Object is missing). ".
                    'You should pass a User Object to the evaluation methods like `getValue()` in order to make targeting work properly. '.
                    'Read more: https://configcat.com/docs/advanced/user-object/', [
                        'event_id' => 3001,
                    ]);
            }

            $result = SettingValue::get($json[Setting::VALUE], $settingType);
            $variationId = $json[Setting::VARIATION_ID] ?? '';
            $logCollector->add('Returning '.Utils::getStringRepresentation($result).'.');

            return new EvaluationResult($result, $variationId, null, null);
        }

        $logCollector->add('User object: '.$user);
        if (isset($json[Setting::TARGETING_RULES]) && !empty($json[Setting::TARGETING_RULES])) {
            foreach ($json[Setting::TARGETING_RULES] as $targetingRule) {
                $rule = $targetingRule[TargetingRule::CONDITIONS][0][ConditionContainer::USER_CONDITION];
                $simpleValue = $targetingRule[TargetingRule::SIMPLE_VALUE];

                $comparisonAttribute = $rule[UserCondition::COMPARISON_ATTRIBUTE];
                $comparator = $rule[UserCondition::COMPARATOR];
                $value = SettingValue::get($simpleValue[SettingValueContainer::VALUE], $settingType);
                $variationId = $simpleValue[SettingValueContainer::VARIATION_ID] ?? '';
                $userValue = $user->getAttribute($comparisonAttribute);

                $comparisonValue = $rule[UserCondition::STRING_COMPARISON_VALUE] ?? $rule[UserCondition::NUMBER_COMPARISON_VALUE] ?? $rule[UserCondition::STRINGLIST_COMPARISON_VALUE];
                if (empty($comparisonValue) || (!is_numeric($userValue) && empty($userValue))) {
                    $logCollector->add($this->logNoMatch(
                        $comparisonAttribute,
                        $userValue,
                        $comparator,
                        (string) json_encode($comparisonValue)
                    ));

                    continue;
                }

                switch ($comparator) {
                    // IS ONE OF
                    case 0:
                        $split = $comparisonValue;
                        if (in_array($userValue, $split, true)) {
                            $logCollector->add($this->logMatch(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                (string) json_encode($comparisonValue),
                                $value
                            ));

                            return new EvaluationResult($value, $variationId, $targetingRule, null);
                        }

                        break;

                        // IS NOT ONE OF
                    case 1:
                        $split = $comparisonValue;
                        if (!in_array($userValue, $split, true)) {
                            $logCollector->add($this->logMatch(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                (string) json_encode($comparisonValue),
                                $value
                            ));

                            return new EvaluationResult($value, $variationId, $targetingRule, null);
                        }

                        break;

                        // CONTAINS
                    case 2:
                        if (Utils::strContains($userValue, $comparisonValue[0])) {
                            $logCollector->add($this->logMatch(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                (string) json_encode($comparisonValue),
                                $value
                            ));

                            return new EvaluationResult($value, $variationId, $targetingRule, null);
                        }

                        break;

                        // DOES NOT CONTAIN
                    case 3:
                        if (!Utils::strContains($userValue, $comparisonValue[0])) {
                            $logCollector->add($this->logMatch(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                (string) json_encode($comparisonValue),
                                $value
                            ));

                            return new EvaluationResult($value, $variationId, $targetingRule, null);
                        }

                        break;

                        // IS ONE OF, IS NOT ONE OF (SemVer)
                    case 4:
                    case 5:
                        $split = $comparisonValue;

                        try {
                            $matched = false;
                            foreach ($split as $semVer) {
                                if (empty($semVer)) {
                                    continue;
                                }

                                $matched = Version::equal($userValue, $semVer) || $matched;
                            }

                            if (($matched && 4 == $comparator) || (!$matched && 5 == $comparator)) {
                                $logCollector->add($this->logMatch(
                                    $comparisonAttribute,
                                    $userValue,
                                    $comparator,
                                    (string) json_encode($comparisonValue),
                                    $value
                                ));

                                return new EvaluationResult($value, $variationId, $targetingRule, null);
                            }
                        } catch (SemverException) {
                            $logCollector->add($this->logMatch(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                (string) json_encode($comparisonValue),
                                $value
                            ));

                            break;
                        }

                        break;

                        // LESS THAN, LESS THAN OR EQUALS TO, GREATER THAN, GREATER THAN OR EQUALS TO (SemVer)
                    case 6:
                    case 7:
                    case 8:
                    case 9:
                        try {
                            if ((6 == $comparator
                                    && Version::lessThan($userValue, $comparisonValue))
                                || (7 == $comparator
                                    && Version::lessThanOrEqual($userValue, $comparisonValue))
                                || (8 == $comparator
                                    && Version::greaterThan($userValue, $comparisonValue))
                                || (9 == $comparator
                                    && Version::greaterThanOrEqual($userValue, $comparisonValue))) {
                                $logCollector->add($this->logMatch(
                                    $comparisonAttribute,
                                    $userValue,
                                    $comparator,
                                    (string) json_encode($comparisonValue),
                                    $value
                                ));

                                return new EvaluationResult($value, $variationId, $targetingRule, null);
                            }
                        } catch (SemverException $exception) {
                            $logCollector->add($this->logFormatError(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                (string) json_encode($comparisonValue),
                                $exception
                            ));

                            break;
                        }

                        break;

                        // LESS THAN, LESS THAN OR EQUALS TO, GREATER THAN, GREATER THAN OR EQUALS TO (Number)
                    case 10:
                    case 11:
                    case 12:
                    case 13:
                    case 14:
                    case 15:
                        $userDouble = str_replace(',', '.', $userValue);
                        $comparisonDouble = $comparisonValue;
                        if (!is_numeric($userDouble)) {
                            $logCollector->add($this->logFormatErrorWithMessage(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                (string) json_encode($comparisonValue),
                                $userDouble.'is not a valid number.'
                            ));

                            break;
                        }

                        if (!is_numeric($comparisonDouble)) {
                            $logCollector->add($this->logFormatErrorWithMessage(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                (string) json_encode($comparisonValue),
                                $comparisonDouble.'is not a valid number.'
                            ));

                            break;
                        }

                        $userDoubleValue = floatval($userDouble);
                        $comparisonDoubleValue = floatval($comparisonDouble);

                        if ((10 == $comparator && $userDoubleValue == $comparisonDoubleValue)
                            || (11 == $comparator && $userDoubleValue != $comparisonDoubleValue)
                            || (12 == $comparator && $userDoubleValue < $comparisonDoubleValue)
                            || (13 == $comparator && $userDoubleValue <= $comparisonDoubleValue)
                            || (14 == $comparator && $userDoubleValue > $comparisonDoubleValue)
                            || (15 == $comparator && $userDoubleValue >= $comparisonDoubleValue)) {
                            $logCollector->add($this->logMatch(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                (string) json_encode($comparisonValue),
                                $value
                            ));

                            return new EvaluationResult($value, $variationId, $targetingRule, null);
                        }

                        break;

                        // IS ONE OF (Sensitive)
                    case 16:
                        $split = $comparisonValue;
                        if (in_array(hash('sha256', $userValue.$json[Setting::CONFIG_JSON_SALT].$key), $split, true)) {
                            $logCollector->add($this->logMatch(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                (string) json_encode($comparisonValue),
                                $value
                            ));

                            return new EvaluationResult($value, $variationId, $targetingRule, null);
                        }

                        break;

                        // IS NOT ONE OF (Sensitive)
                    case 17:
                        $split = $comparisonValue;
                        if (!in_array(hash('sha256', $userValue.$json[Setting::CONFIG_JSON_SALT].$key), $split, true)) {
                            $logCollector->add($this->logMatch(
                                $comparisonAttribute,
                                $userValue,
                                $comparator,
                                (string) json_encode($comparisonValue),
                                $value
                            ));

                            return new EvaluationResult($value, $variationId, $targetingRule, null);
                        }

                        break;
                }
                $logCollector->add($this->logNoMatch($comparisonAttribute, $userValue, $comparator, (string) json_encode($comparisonValue)));
            }
        }

        if (isset($json[Setting::PERCENTAGE_OPTIONS])
            && !empty($json[Setting::PERCENTAGE_OPTIONS])) {
            $hashCandidate = $key.$user->getIdentifier();
            $stringHash = substr(sha1($hashCandidate), 0, 7);
            $intHash = intval($stringHash, 16);
            $scale = $intHash % 100;

            $bucket = 0;
            foreach ($json[Setting::PERCENTAGE_OPTIONS] as $rule) {
                $bucket += $rule[PercentageOption::PERCENTAGE];
                if ($scale < $bucket) {
                    $result = SettingValue::get($rule[PercentageOption::VALUE], $settingType);
                    $variationId = $rule[PercentageOption::VARIATION_ID];
                    $logCollector->add(
                        'Evaluating % options. Returning '.Utils::getStringRepresentation($result).'.'
                    );

                    return new EvaluationResult($result, $variationId, null, $rule);
                }
            }
        }

        $result = SettingValue::get($json[Setting::VALUE], $settingType);
        $variationId = $json[Setting::VARIATION_ID] ?? '';
        $logCollector->add('Returning '.Utils::getStringRepresentation($result).'.');

        return new EvaluationResult($result, $variationId, null, null);
    }

    private function logMatch(
        string $comparisonAttribute,
        string $userValue,
        int $comparator,
        string $comparisonValue,
        mixed $value
    ): string {
        return 'Evaluating rule: ['.$comparisonAttribute.':'.$userValue.'] '.
            '['.$this->comparatorTexts[$comparator].'] '.
            '['.$comparisonValue.'] => match, returning: '.Utils::getStringRepresentation($value).'.';
    }

    private function logNoMatch(
        string $comparisonAttribute,
        ?string $userValue,
        int $comparator,
        string $comparisonValue
    ): string {
        return 'Evaluating rule: ['.$comparisonAttribute.':'.$userValue.'] '.
            '['.$this->comparatorTexts[$comparator].'] '.
            '['.$comparisonValue.'] => no match.';
    }

    private function logFormatError(
        string $comparisonAttribute,
        string $userValue,
        int $comparator,
        string $comparisonValue,
        Exception $exception
    ): string {
        $message = 'Evaluating rule: ['.$comparisonAttribute.':'.$userValue.'] '.
            '['.$this->comparatorTexts[$comparator].'] '.
            '['.$comparisonValue.'] => SKIP rule. Validation error: '.$exception->getMessage().'.';
        $this->logger->warning($message, ['exception' => $exception]);

        return $message;
    }

    private function logFormatErrorWithMessage(
        string $comparisonAttribute,
        string $userValue,
        int $comparator,
        string $comparisonValue,
        string $message
    ): string {
        $message = 'Evaluating rule: ['.$comparisonAttribute.':'.$userValue.'] '.
            '['.$this->comparatorTexts[$comparator].'] '.
            '['.$comparisonValue.'] => SKIP rule. Validation error: '.$message.'.';
        $this->logger->warning($message);

        return $message;
    }
}
