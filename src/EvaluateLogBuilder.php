<?php

declare(strict_types=1);

namespace ConfigCat;

use ConfigCat\ConfigJson\PrerequisiteFlagComparator;
use ConfigCat\ConfigJson\PrerequisiteFlagCondition;
use ConfigCat\ConfigJson\Segment;
use ConfigCat\ConfigJson\SegmentComparator;
use ConfigCat\ConfigJson\SegmentCondition;
use ConfigCat\ConfigJson\SettingType;
use ConfigCat\ConfigJson\SettingValue;
use ConfigCat\ConfigJson\SettingValueContainer;
use ConfigCat\ConfigJson\TargetingRule;
use ConfigCat\ConfigJson\UserComparator;
use ConfigCat\ConfigJson\UserCondition;
use stdClass;

/**
 * @internal
 */
final class EvaluateLogBuilder
{
    public const INVALID_NAME_PLACEHOLDER = '<invalid name>';
    public const INVALID_REFERENCE_PLACEHOLDER = '<invalid reference>';
    public const INVALID_OPERATOR_PLACEHOLDER = '<invalid operator>';
    public const INVALID_VALUE_PLACEHOLDER = '<invalid value>';

    private const VALUE_TEXT = 'value';
    private const VALUES_TEXT = 'values';

    private const STRING_LIST_MAX_COUNT = 10;

    private string $log = '';
    private string $indent = '';

    public function __toString(): string
    {
        return $this->log;
    }

    public function resetIndent(): self
    {
        $this->indent = '';

        return $this;
    }

    public function increaseIndent(): self
    {
        $this->indent .= '  ';

        return $this;
    }

    public function decreaseIndent(): self
    {
        $this->indent = substr($this->indent, 2);

        return $this;
    }

    public function newLine(string $text = ''): self
    {
        $this->log .= PHP_EOL.$this->indent.$text;

        return $this;
    }

    public function append(string $text): self
    {
        $this->log .= $text;

        return $this;
    }

    /**
     * @param array<string, mixed> $condition
     */
    public function appendUserCondition(array $condition): self
    {
        $comparisonAttribute = $condition[UserCondition::COMPARISON_ATTRIBUTE] ?? null;
        if (!is_string($comparisonAttribute)) {
            $comparisonAttribute = self::INVALID_NAME_PLACEHOLDER;
        }

        $comparator = UserComparator::tryFrom($condition[UserCondition::COMPARATOR] ?? null);

        switch ($comparator) {
            case UserComparator::TEXT_IS_ONE_OF:
            case UserComparator::TEXT_IS_NOT_ONE_OF:
            case UserComparator::TEXT_CONTAINS_ANY_OF:
            case UserComparator::TEXT_NOT_CONTAINS_ANY_OF:
            case UserComparator::SEMVER_IS_ONE_OF:
            case UserComparator::SEMVER_IS_NOT_ONE_OF:
            case UserComparator::TEXT_STARTS_WITH_ANY_OF:
            case UserComparator::TEXT_NOT_STARTS_WITH_ANY_OF:
            case UserComparator::TEXT_ENDS_WITH_ANY_OF:
            case UserComparator::TEXT_NOT_ENDS_WITH_ANY_OF:
            case UserComparator::ARRAY_CONTAINS_ANY_OF:
            case UserComparator::ARRAY_NOT_CONTAINS_ANY_OF:
                return $this->appendUserConditionStringList(
                    $comparisonAttribute,
                    $comparator,
                    $condition[UserCondition::STRINGLIST_COMPARISON_VALUE] ?? null,
                    false
                );

            case UserComparator::SEMVER_LESS:
            case UserComparator::SEMVER_LESS_OR_EQUALS:
            case UserComparator::SEMVER_GREATER:
            case UserComparator::SEMVER_GREATER_OR_EQUALS:
            case UserComparator::TEXT_EQUALS:
            case UserComparator::TEXT_NOT_EQUALS:
                return $this->appendUserConditionString(
                    $comparisonAttribute,
                    $comparator,
                    $condition[UserCondition::STRING_COMPARISON_VALUE] ?? null,
                    false
                );

            case UserComparator::NUMBER_EQUALS:
            case UserComparator::NUMBER_NOT_EQUALS:
            case UserComparator::NUMBER_LESS:
            case UserComparator::NUMBER_LESS_OR_EQUALS:
            case UserComparator::NUMBER_GREATER:
            case UserComparator::NUMBER_GREATER_OR_EQUALS:
                return $this->appendUserConditionNumber(
                    $comparisonAttribute,
                    $comparator,
                    $condition[UserCondition::NUMBER_COMPARISON_VALUE] ?? null
                );

            case UserComparator::SENSITIVE_TEXT_IS_ONE_OF:
            case UserComparator::SENSITIVE_TEXT_IS_NOT_ONE_OF:
            case UserComparator::SENSITIVE_TEXT_STARTS_WITH_ANY_OF:
            case UserComparator::SENSITIVE_TEXT_NOT_STARTS_WITH_ANY_OF:
            case UserComparator::SENSITIVE_TEXT_ENDS_WITH_ANY_OF:
            case UserComparator::SENSITIVE_TEXT_NOT_ENDS_WITH_ANY_OF:
            case UserComparator::SENSITIVE_ARRAY_CONTAINS_ANY_OF:
            case UserComparator::SENSITIVE_ARRAY_NOT_CONTAINS_ANY_OF:
                return $this->appendUserConditionStringList(
                    $comparisonAttribute,
                    $comparator,
                    $condition[UserCondition::STRINGLIST_COMPARISON_VALUE] ?? null,
                    true
                );

            case UserComparator::DATETIME_BEFORE:
            case UserComparator::DATETIME_AFTER:
                return $this->appendUserConditionNumber(
                    $comparisonAttribute,
                    $comparator,
                    $condition[UserCondition::NUMBER_COMPARISON_VALUE] ?? null,
                    true
                );

            case UserComparator::SENSITIVE_TEXT_EQUALS:
            case UserComparator::SENSITIVE_TEXT_NOT_EQUALS:
                return $this->appendUserConditionString(
                    $comparisonAttribute,
                    $comparator,
                    $condition[UserCondition::STRING_COMPARISON_VALUE] ?? null,
                    true
                );

            default:
                $comparisonValue = $condition[UserCondition::STRING_COMPARISON_VALUE]
                    ?? $condition[UserCondition::NUMBER_COMPARISON_VALUE]
                    ?? $condition[UserCondition::STRINGLIST_COMPARISON_VALUE]
                    ?? null;

                if (is_string($comparisonValue) || is_double($comparisonValue) || is_int($comparisonValue) || Utils::isStringList($comparisonValue)) {
                    $comparisonValue = null;
                }

                return $this->appendUserConditionCore($comparisonAttribute, $comparator, $comparisonValue);
        }
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $settings
     */
    public function appendPrerequisiteFlagCondition(array $condition, array $settings): self
    {
        $prerequisiteFlagKey = $condition[PrerequisiteFlagCondition::PREREQUISITE_FLAG_KEY] ?? null;
        if (!is_string($prerequisiteFlagKey)) {
            $prerequisiteFlagKey = self::INVALID_NAME_PLACEHOLDER;
        } elseif (!array_key_exists($prerequisiteFlagKey, $settings)) {
            $prerequisiteFlagKey = self::INVALID_REFERENCE_PLACEHOLDER;
        }

        $comparator = PrerequisiteFlagComparator::tryFrom($condition[PrerequisiteFlagCondition::COMPARATOR] ?? null);
        $comparatorFormatted = self::formatPrerequisiteFlagComparator($comparator);

        $comparisonValue = SettingValue::infer($condition[PrerequisiteFlagCondition::COMPARISON_VALUE] ?? null);
        $comparisonValueFormatted = self::formatSettingValue($comparisonValue);

        return $this->append("Flag '{$prerequisiteFlagKey}' {$comparatorFormatted} '{$comparisonValueFormatted}'");
    }

    /**
     * @param array<string, mixed> $condition
     */
    public function appendSegmentCondition(array $condition, mixed $segments): self
    {
        $segmentIndex = $condition[SegmentCondition::SEGMENT_INDEX] ?? null;
        $segment = $segments[is_int($segmentIndex) ? $segmentIndex : null] ?? null;
        if (isset($segment)) {
            $segmentName = $segment[Segment::NAME] ?? null;
            if (!is_string($segmentName) || '' === $segmentName) {
                $segmentName = self::INVALID_NAME_PLACEHOLDER;
            }
        } else {
            $segmentName = self::INVALID_REFERENCE_PLACEHOLDER;
        }

        $comparator = SegmentComparator::tryFrom($condition[SegmentCondition::COMPARATOR] ?? null);
        $comparatorFormatted = self::formatSegmentComparator($comparator);

        return $this->append("User {$comparatorFormatted} '{$segmentName}'");
    }

    public function appendConditionResult(bool $result): self
    {
        return $this->append($result ? 'true' : 'false');
    }

    public function appendConditionConsequence(bool $result): self
    {
        $this->append(' => ')->appendConditionResult($result);

        return $result ? $this : $this->append(', skipping the remaining AND conditions');
    }

    /**
     * @param array<string, mixed> $targetingRule
     */
    public function appendTargetingRuleThenPart(array $targetingRule, SettingType|stdClass $settingType, bool $newLine): self
    {
        ($newLine ? $this->newLine() : $this->append(' '))
            ->append('THEN')
        ;

        if (!TargetingRule::hasPercentageOptions($targetingRule, false)) {
            $simpleValue = SettingValue::get($targetingRule[TargetingRule::SIMPLE_VALUE][SettingValueContainer::VALUE] ?? null, $settingType, false);
            $simpleValueFormatted = self::formatSettingValue($simpleValue);

            return $this->append(" '{$simpleValueFormatted}'");
        }

        return $this->append(' % options');
    }

    /**
     * @param array<string, mixed> $targetingRule
     */
    public function appendTargetingRuleConsequence(array $targetingRule, SettingType|stdClass $settingType, bool|string $isMatchOrError, bool $newLine): self
    {
        $this->increaseIndent();

        $this->appendTargetingRuleThenPart($targetingRule, $settingType, $newLine)
            ->append(' => ')->append(true === $isMatchOrError ? 'MATCH, applying rule' : (false === $isMatchOrError ? 'no match' : $isMatchOrError))
        ;

        return $this->decreaseIndent();
    }

    public static function formatSettingValue(mixed $value): string
    {
        if (!isset($value)) {
            return self::INVALID_VALUE_PLACEHOLDER;
        }

        return Utils::getStringRepresentation($value);
    }

    public static function formatUserComparator(?UserComparator $comparator): string
    {
        switch ($comparator) {
            case UserComparator::TEXT_IS_ONE_OF:
            case UserComparator::SENSITIVE_TEXT_IS_ONE_OF:
            case UserComparator::SEMVER_IS_ONE_OF: return 'IS ONE OF';

            case UserComparator::TEXT_IS_NOT_ONE_OF:
            case UserComparator::SENSITIVE_TEXT_IS_NOT_ONE_OF:
            case UserComparator::SEMVER_IS_NOT_ONE_OF: return 'IS NOT ONE OF';

            case UserComparator::TEXT_CONTAINS_ANY_OF: return 'CONTAINS ANY OF';

            case UserComparator::TEXT_NOT_CONTAINS_ANY_OF: return 'NOT CONTAINS ANY OF';

            case UserComparator::SEMVER_LESS:
            case UserComparator::NUMBER_LESS: return '<';

            case UserComparator::SEMVER_LESS_OR_EQUALS:
            case UserComparator::NUMBER_LESS_OR_EQUALS: return '<=';

            case UserComparator::SEMVER_GREATER:
            case UserComparator::NUMBER_GREATER: return '>';

            case UserComparator::SEMVER_GREATER_OR_EQUALS:
            case UserComparator::NUMBER_GREATER_OR_EQUALS: return '>=';

            case UserComparator::NUMBER_EQUALS: return '=';

            case UserComparator::NUMBER_NOT_EQUALS: return '!=';

            case UserComparator::DATETIME_BEFORE: return 'BEFORE';

            case UserComparator::DATETIME_AFTER: return 'AFTER';

            case UserComparator::TEXT_EQUALS:
            case UserComparator::SENSITIVE_TEXT_EQUALS: return 'EQUALS';

            case UserComparator::TEXT_NOT_EQUALS:
            case UserComparator::SENSITIVE_TEXT_NOT_EQUALS: return 'NOT EQUALS';

            case UserComparator::TEXT_STARTS_WITH_ANY_OF:
            case UserComparator::SENSITIVE_TEXT_STARTS_WITH_ANY_OF: return 'STARTS WITH ANY OF';

            case UserComparator::TEXT_NOT_STARTS_WITH_ANY_OF:
            case UserComparator::SENSITIVE_TEXT_NOT_STARTS_WITH_ANY_OF: return 'NOT STARTS WITH ANY OF';

            case UserComparator::TEXT_ENDS_WITH_ANY_OF:
            case UserComparator::SENSITIVE_TEXT_ENDS_WITH_ANY_OF: return 'ENDS WITH ANY OF';

            case UserComparator::TEXT_NOT_ENDS_WITH_ANY_OF:
            case UserComparator::SENSITIVE_TEXT_NOT_ENDS_WITH_ANY_OF: return 'NOT ENDS WITH ANY OF';

            case UserComparator::ARRAY_CONTAINS_ANY_OF:
            case UserComparator::SENSITIVE_ARRAY_CONTAINS_ANY_OF: return 'ARRAY CONTAINS ANY OF';

            case UserComparator::ARRAY_NOT_CONTAINS_ANY_OF:
            case UserComparator::SENSITIVE_ARRAY_NOT_CONTAINS_ANY_OF: return 'ARRAY NOT CONTAINS ANY OF';

            default: return self::INVALID_OPERATOR_PLACEHOLDER;
        }
    }

    public static function formatPrerequisiteFlagComparator(?PrerequisiteFlagComparator $comparator): string
    {
        switch ($comparator) {
            case PrerequisiteFlagComparator::EQUALS: return 'EQUALS';

            case PrerequisiteFlagComparator::NOT_EQUALS: return 'NOT EQUALS';

            default: return self::INVALID_OPERATOR_PLACEHOLDER;
        }
    }

    public static function formatSegmentComparator(?SegmentComparator $comparator): string
    {
        switch ($comparator) {
            case SegmentComparator::IS_IN: return 'IS IN SEGMENT';

            case SegmentComparator::IS_NOT_IN: return 'IS NOT IN SEGMENT';

            default: return self::INVALID_OPERATOR_PLACEHOLDER;
        }
    }

    /**
     * @param array<string, mixed> $condition
     */
    public static function formatUserCondition(array $condition): string
    {
        $logBuilder = new self();

        return (string) $logBuilder->appendUserCondition($condition);
    }

    private function appendUserConditionCore(string $comparisonAttribute, ?UserComparator $comparator, ?string $comparisonValue): self
    {
        $comparatorFormatted = self::formatUserComparator($comparator);
        $comparisonValue ??= self::INVALID_VALUE_PLACEHOLDER;

        return $this->append("User.{$comparisonAttribute} {$comparatorFormatted} '{$comparisonValue}'");
    }

    private function appendUserConditionString(string $comparisonAttribute, ?UserComparator $comparator, mixed $comparisonValue, bool $isSensitive): self
    {
        if (!is_string($comparisonValue)) {
            return $this->appendUserConditionCore($comparisonAttribute, $comparator, null);
        }

        return $this->appendUserConditionCore($comparisonAttribute, $comparator, !$isSensitive ? $comparisonValue : '<hashed value>');
    }

    private function appendUserConditionStringList(string $comparisonAttribute, ?UserComparator $comparator, mixed $comparisonValue, bool $isSensitive): self
    {
        if (!Utils::isStringList($comparisonValue)) {
            return $this->appendUserConditionCore($comparisonAttribute, $comparator, null);
        }

        $comparatorFormatted = self::formatUserComparator($comparator);
        if ($isSensitive) {
            $comparisonValueCount = count($comparisonValue);
            $valueText = 1 === $comparisonValueCount ? self::VALUE_TEXT : self::VALUES_TEXT;

            return $this->append("User.{$comparisonAttribute} {$comparatorFormatted} [<{$comparisonValueCount} hashed {$valueText}>]");
        }

        $comparisonValueFormatted = Utils::formatStringList($comparisonValue, self::STRING_LIST_MAX_COUNT, function ($count) {
            $valueText = 1 === $count ? self::VALUE_TEXT : self::VALUES_TEXT;

            return ", ... <{$count} more {$valueText}>";
        });

        return $this->append("User.{$comparisonAttribute} {$comparatorFormatted} [{$comparisonValueFormatted}]");
    }

    private function appendUserConditionNumber(string $comparisonAttribute, ?UserComparator $comparator, mixed $comparisonValue, bool $isDateTime = false): self
    {
        if (!(is_double($comparisonValue) || is_int($comparisonValue))) {
            return $this->appendUserConditionCore($comparisonAttribute, $comparator, null);
        }

        $comparatorFormatted = self::formatUserComparator($comparator);

        if ($isDateTime && ($dateTime = Utils::dateTimeFromUnixSeconds($comparisonValue))) {
            $dateIsoString = $dateTime->format('Y-m-d\\TH:i:s.vp');

            return $this->append("User.{$comparisonAttribute} {$comparatorFormatted} '{$comparisonValue}' ({$dateIsoString} UTC)");
        }

        return $this->append("User.{$comparisonAttribute} {$comparatorFormatted} '{$comparisonValue}'");
    }
}
