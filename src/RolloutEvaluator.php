<?php

declare(strict_types=1);

namespace ConfigCat;

use ConfigCat\ConfigJson\ConditionContainer;
use ConfigCat\ConfigJson\PercentageOption;
use ConfigCat\ConfigJson\PrerequisiteFlagComparator;
use ConfigCat\ConfigJson\PrerequisiteFlagCondition;
use ConfigCat\ConfigJson\Segment;
use ConfigCat\ConfigJson\SegmentComparator;
use ConfigCat\ConfigJson\SegmentCondition;
use ConfigCat\ConfigJson\Setting;
use ConfigCat\ConfigJson\SettingType;
use ConfigCat\ConfigJson\SettingValue;
use ConfigCat\ConfigJson\SettingValueContainer;
use ConfigCat\ConfigJson\TargetingRule;
use ConfigCat\ConfigJson\UserComparator;
use ConfigCat\ConfigJson\UserCondition;
use ConfigCat\Log\InternalLogger;
use ConfigCat\Log\LogLevel;
use DateTimeInterface;
use LogicException;
use stdClass;
use Throwable;
use UnexpectedValueException;
use z4kn4fein\SemVer\Version;

/**
 * @internal
 */
final class EvaluateContext
{
    public bool $isMissingUserObjectLogged;
    public bool $isMissingUserObjectAttributeLogged;

    public ?EvaluateLogBuilder $logBuilder = null; // initialized by RolloutEvaluator.evaluate

    private null|SettingType|stdClass $settingType = null;

    /**
     * @var null|list<string>
     */
    private null|array $visitedFlags = null;

    /**
     * @param string               $key      the key of the setting to evaluate
     * @param mixed                $setting  the definition of the setting to evaluate
     * @param ?User                $user     the User Object
     * @param array<string, mixed> $settings the map of settings
     */
    public function __construct(
        public readonly string $key,
        public readonly mixed $setting,
        public readonly ?User $user,
        public readonly array $settings
    ) {
        $this->isMissingUserObjectLogged = $this->isMissingUserObjectAttributeLogged = false;
    }

    public static function forPrerequisiteFlag(string $key, mixed $setting, EvaluateContext $dependentFlagContext): EvaluateContext
    {
        $context = new EvaluateContext($key, $setting, $dependentFlagContext->user, $dependentFlagContext->settings);
        $context->visitedFlags = &$dependentFlagContext->getVisitedFlags(); // crucial to use `getVisitedFlags` here to make sure the list is created!
        $context->logBuilder = $dependentFlagContext->logBuilder;

        return $context;
    }

    public function getSettingType(): SettingType|stdClass
    {
        return $this->settingType ??= Setting::getType($this->setting); // @phpstan-ignore-line
    }

    /**
     * @return list<string>
     */
    public function &getVisitedFlags(): array
    {
        $this->visitedFlags ??= [];

        return $this->visitedFlags;
    }
}

/**
 * @internal
 */
final class EvaluateResult
{
    /**
     * @param array<string, mixed>      $selectedValue
     * @param null|array<string, mixed> $matchedTargetingRule
     * @param null|array<string, mixed> $matchedPercentageOption
     */
    public function __construct(
        public readonly array $selectedValue,
        public readonly ?array $matchedTargetingRule = null,
        public readonly ?array $matchedPercentageOption = null
    ) {}
}

/**
 * @internal
 */
final class RolloutEvaluator
{
    private const TARGETING_RULE_IGNORED_MESSAGE = 'The current targeting rule is ignored and the evaluation continues with the next rule.';
    private const MISSING_USER_OBJECT_ERROR = 'cannot evaluate, User Object is missing';
    private const MISSING_USER_ATTRIBUTE_ERROR = 'cannot evaluate, the User.%s attribute is missing';
    private const INVALID_USER_ATTRIBUTE_ERROR = 'cannot evaluate, the User.%s attribute is invalid (%s)';

    /**
     * @param InternalLogger $logger the logger instance
     */
    public function __construct(private readonly InternalLogger $logger) {}

    /**
     * @param mixed           $defaultValue the value to return in case of failure
     * @param EvaluateContext $context      the context object
     *
     * @return EvaluateResult the result of the evaluation
     *
     * @throws UnexpectedValueException
     */
    public function evaluate(mixed $defaultValue, EvaluateContext $context, mixed &$returnValue): EvaluateResult
    {
        $logBuilder = $context->logBuilder;

        // Building the evaluation log is expensive, so let's not do it if it wouldn't be logged anyway.
        if ($this->logger->shouldLog(LogLevel::INFO, [])) {
            $context->logBuilder = $logBuilder = new EvaluateLogBuilder();

            $logBuilder->append("Evaluating '{$context->key}'");

            if (isset($context->user)) {
                $logBuilder->append(" for User '{$context->user}'");
            }

            $logBuilder->increaseIndent();
        }

        try {
            Setting::ensure($context->setting);

            $settingType = $context->getSettingType();
            $result = $this->evaluateSetting($context);
            $returnValue = SettingValue::get($result->selectedValue[SettingValueContainer::VALUE] ?? null, $settingType);

            return $result;
        } catch (Throwable $ex) {
            $logBuilder?->resetIndent()->increaseIndent();

            $returnValue = $defaultValue;

            throw $ex;
        } finally {
            if (isset($logBuilder)) {
                $returnValueFormatted = EvaluateLogBuilder::formatSettingValue($returnValue);
                $logBuilder->newLine("Returning '{$returnValueFormatted}'.")
                    ->decreaseIndent()
                ;
                $this->logger->info((string) $logBuilder, [
                    'event_id' => 5000,
                ]);
            }

            if (!isset($ex)) {
                $this->checkDefaultValueTypeMismatch(
                    $returnValue,
                    $defaultValue,
                    $settingType // @phpstan-ignore-line
                );
            }
        }
    }

    private function evaluateSetting(EvaluateContext $context): EvaluateResult
    {
        $targetingRules = TargetingRule::ensureList($context->setting[Setting::TARGETING_RULES] ?? []);
        if (!empty($targetingRules) && ($evaluateResult = $this->evaluateTargetingRules($targetingRules, $context))) {
            return $evaluateResult;
        }

        $percentageOptions = PercentageOption::ensureList($context->setting[Setting::PERCENTAGE_OPTIONS] ?? []);
        if (!empty($percentageOptions) && ($evaluateResult = $this->evaluatePercentageOptions($percentageOptions, null, $context))) {
            return $evaluateResult;
        }

        return new EvaluateResult($context->setting);
    }

    /**
     * @param list<array<string, mixed>> $targetingRules
     */
    private function evaluateTargetingRules(array $targetingRules, EvaluateContext $context): null|EvaluateResult
    {
        $logBuilder = $context->logBuilder;

        $logBuilder?->newLine('Evaluating targeting rules and applying the first match if any:');

        foreach ($targetingRules as $targetingRule) {
            TargetingRule::ensure($targetingRule);

            $conditions = ConditionContainer::ensureList($targetingRule[TargetingRule::CONDITIONS] ?? []);

            $isMatchOrError = $this->evaluateConditions($conditions, ConditionContainer::conditionAccessor(), $targetingRule, $context->key, $context);
            if (true !== $isMatchOrError) {
                if (is_string($isMatchOrError)) {
                    $logBuilder?->increaseIndent()
                        ->newLine(self::TARGETING_RULE_IGNORED_MESSAGE)
                        ->decreaseIndent()
                    ;
                }

                continue;
            }

            if (!TargetingRule::hasPercentageOptions($targetingRule)) {
                $simpleValue = $targetingRule[TargetingRule::SIMPLE_VALUE];

                return new EvaluateResult($simpleValue, $targetingRule);
            }

            $percentageOptions = $targetingRule[TargetingRule::PERCENTAGE_OPTIONS];

            $logBuilder?->increaseIndent();

            $evaluateResult = $this->evaluatePercentageOptions($percentageOptions, $targetingRule, $context);
            if ($evaluateResult) {
                $logBuilder?->decreaseIndent();

                return $evaluateResult;
            }

            $logBuilder?->newLine(self::TARGETING_RULE_IGNORED_MESSAGE)
                ->decreaseIndent()
            ;
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $percentageOptions
     * @param array<string, mixed>       $matchedTargetingRule
     */
    private function evaluatePercentageOptions(array $percentageOptions, ?array $matchedTargetingRule, EvaluateContext $context): null|EvaluateResult
    {
        $logBuilder = $context->logBuilder;

        if (!isset($context->user)) {
            $logBuilder?->newLine('Skipping % options because the User Object is missing.');

            if (!$context->isMissingUserObjectLogged) {
                $this->logUserObjectIsMissing($context->key);
                $context->isMissingUserObjectLogged = true;
            }

            return null;
        }

        $percentageOptionsAttributeName = $context->setting[Setting::PERCENTAGE_OPTIONS_ATTRIBUTE] ?? null;
        if (!isset($percentageOptionsAttributeName)) {
            $percentageOptionsAttributeName = User::IDENTIFIER_ATTRIBUTE;
            $percentageOptionsAttributeValue = $context->user->getIdentifier();
        } elseif (is_string($percentageOptionsAttributeName)) {
            $percentageOptionsAttributeValue = $context->user->getAttribute($percentageOptionsAttributeName);
        } else {
            throw new UnexpectedValueException('Percentage evaluation attribute is invalid.');
        }

        if (!isset($percentageOptionsAttributeValue)) {
            $logBuilder?->newLine()->append("Skipping % options because the User.{$percentageOptionsAttributeName} attribute is missing.");

            if (!$context->isMissingUserObjectAttributeLogged) {
                $this->logUserObjectAttributeIsMissingPercentage($context->key, $percentageOptionsAttributeName);
                $context->isMissingUserObjectAttributeLogged = true;
            }

            return null;
        }

        $logBuilder?->newLine()->append("Evaluating % options based on the User.{$percentageOptionsAttributeName} attribute:");

        $sha1 = sha1($context->key.self::userAttributeValueToString($percentageOptionsAttributeValue));
        $hashValue = intval(substr($sha1, 0, 7), 16) % 100;

        $logBuilder?->newLine()->append("- Computing hash in the [0..99] range from User.{$percentageOptionsAttributeName} => {$hashValue} (this value is sticky and consistent across all SDKs)");

        $bucket = 0;
        $optionNumber = 1;

        foreach ($percentageOptions as $percentageOption) {
            PercentageOption::ensure($percentageOption);

            $percentage = $percentageOption[PercentageOption::PERCENTAGE] ?? null;
            if (!(is_int($percentage) || is_double($percentage)) || $percentage < 0) {
                throw new UnexpectedValueException('Percentage is missing or invalid.');
            }

            $bucket += $percentage;

            if ($hashValue >= $bucket) {
                ++$optionNumber;

                continue;
            }

            if (isset($logBuilder)) {
                $percentageOptionValue = SettingValue::get($percentageOption[PercentageOption::VALUE] ?? null, $context->getSettingType(), false);
                $percentageOptionValueFormatted = EvaluateLogBuilder::formatSettingValue($percentageOptionValue);
                $logBuilder->newLine()->append("- Hash value {$hashValue} selects % option {$optionNumber} ({$percentage}%), '{$percentageOptionValueFormatted}'.");
            }

            return new EvaluateResult($percentageOption, $matchedTargetingRule, $percentageOption);
        }

        throw new UnexpectedValueException('Sum of percentage option percentages are less than 100.');
    }

    /**
     * @param list<array<string, mixed>>                                           $conditions
     * @param callable(array<string, mixed>, string&): (null|array<string, mixed>) $conditionAccessor
     * @param array<string, mixed>                                                 $targetingRule
     */
    private function evaluateConditions(array $conditions, callable $conditionAccessor, ?array $targetingRule, string $contextSalt, EvaluateContext $context): bool|string
    {
        $result = true;

        $logBuilder = $context->logBuilder;
        $newLineBeforeThen = false;

        $logBuilder?->newLine('- ');

        $i = 0;
        foreach ($conditions as $condition) {
            $conditionType = '';
            $condition = ConditionContainer::ensure($conditionAccessor($condition, $conditionType));

            if (isset($logBuilder)) {
                if (0 === $i) {
                    $logBuilder
                        ->append('IF ')
                        ->increaseIndent()
                    ;
                } else {
                    $logBuilder
                        ->increaseIndent()
                        ->newLine('AND ')
                    ;
                }
            }

            switch ($conditionType) {
                case ConditionContainer::USER_CONDITION:
                    $result = $this->evaluateUserCondition($condition, $contextSalt, $context);
                    $newLineBeforeThen = count($conditions) > 1;

                    break;

                case ConditionContainer::PREREQUISITE_FLAG_CONDITION:
                    $result = $this->evaluatePrerequisiteFlagCondition($condition, $context);
                    $newLineBeforeThen = true;

                    break;

                case ConditionContainer::SEGMENT_CONDITION:
                    $result = $this->evaluateSegmentCondition($condition, $context);
                    $newLineBeforeThen = !is_string($result) || self::MISSING_USER_OBJECT_ERROR !== $result || count($conditions) > 1;

                    break;

                default:
                    throw new LogicException(); // execution should never get here
            }

            $success = true === $result;

            if ($logBuilder) {
                if (!isset($targetingRule) || count($conditions) > 1) {
                    $logBuilder->appendConditionConsequence($success);
                }

                $logBuilder->decreaseIndent();
            }

            if (!$success) {
                break;
            }

            ++$i;
        }

        if ($targetingRule) {
            $logBuilder?->appendTargetingRuleConsequence($targetingRule, $context->getSettingType(), $result, $newLineBeforeThen);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $condition
     */
    private function evaluateUserCondition(array $condition, string $contextSalt, EvaluateContext $context): bool|string
    {
        $logBuilder = $context->logBuilder;
        $logBuilder?->appendUserCondition($condition);

        if (!isset($context->user)) {
            if (!$context->isMissingUserObjectLogged) {
                $this->logUserObjectIsMissing($context->key);
                $context->isMissingUserObjectLogged = true;
            }

            return self::MISSING_USER_OBJECT_ERROR;
        }

        $userAttributeName = $condition[UserCondition::COMPARISON_ATTRIBUTE] ?? null;
        if (!is_string($userAttributeName)) {
            throw new UnexpectedValueException('Comparison attribute is missing or invalid.');
        }
        $userAttributeValue = $context->user->getAttribute($userAttributeName);

        if (!isset($userAttributeValue) || '' === $userAttributeValue) {
            $this->logUserObjectAttributeIsMissingCondition(EvaluateLogBuilder::formatUserCondition($condition), $context->key, $userAttributeName);

            return sprintf(self::MISSING_USER_ATTRIBUTE_ERROR, $userAttributeName);
        }

        $comparator = UserComparator::tryFrom($condition[UserCondition::COMPARATOR] ?? null);

        switch ($comparator) {
            case UserComparator::TEXT_EQUALS:
            case UserComparator::TEXT_NOT_EQUALS:
                $text = $this->getUserAttributeValueAsText($userAttributeName, $userAttributeValue, $condition, $context->key);

                return $this->evaluateTextEquals(
                    $text,
                    $condition[UserCondition::STRING_COMPARISON_VALUE] ?? null,
                    UserComparator::TEXT_NOT_EQUALS === $comparator
                );

            case UserComparator::SENSITIVE_TEXT_EQUALS:
            case UserComparator::SENSITIVE_TEXT_NOT_EQUALS:
                $text = $this->getUserAttributeValueAsText($userAttributeName, $userAttributeValue, $condition, $context->key);

                return $this->evaluateSensitiveTextEquals(
                    $text,
                    $condition[UserCondition::STRING_COMPARISON_VALUE] ?? null,
                    self::ensureConfigJsonSalt($context->setting[Setting::CONFIG_JSON_SALT]),
                    $contextSalt,
                    UserComparator::SENSITIVE_TEXT_NOT_EQUALS === $comparator
                );

            case UserComparator::TEXT_IS_ONE_OF:
            case UserComparator::TEXT_IS_NOT_ONE_OF:
                $text = $this->getUserAttributeValueAsText($userAttributeName, $userAttributeValue, $condition, $context->key);

                return $this->evaluateTextIsOneOf(
                    $text,
                    $condition[UserCondition::STRINGLIST_COMPARISON_VALUE] ?? null,
                    UserComparator::TEXT_IS_NOT_ONE_OF === $comparator
                );

            case UserComparator::SENSITIVE_TEXT_IS_ONE_OF:
            case UserComparator::SENSITIVE_TEXT_IS_NOT_ONE_OF:
                $text = $this->getUserAttributeValueAsText($userAttributeName, $userAttributeValue, $condition, $context->key);

                return $this->evaluateSensitiveTextIsOneOf(
                    $text,
                    $condition[UserCondition::STRINGLIST_COMPARISON_VALUE] ?? null,
                    self::ensureConfigJsonSalt($context->setting[Setting::CONFIG_JSON_SALT]),
                    $contextSalt,
                    UserComparator::SENSITIVE_TEXT_IS_NOT_ONE_OF === $comparator
                );

            case UserComparator::TEXT_STARTS_WITH_ANY_OF:
            case UserComparator::TEXT_NOT_STARTS_WITH_ANY_OF:
                $text = $this->getUserAttributeValueAsText($userAttributeName, $userAttributeValue, $condition, $context->key);

                return $this->evaluateTextSliceEqualsAnyOf(
                    $text,
                    $condition[UserCondition::STRINGLIST_COMPARISON_VALUE] ?? null,
                    true,
                    UserComparator::TEXT_NOT_STARTS_WITH_ANY_OF === $comparator
                );

            case UserComparator::SENSITIVE_TEXT_STARTS_WITH_ANY_OF:
            case UserComparator::SENSITIVE_TEXT_NOT_STARTS_WITH_ANY_OF:
                $text = $this->getUserAttributeValueAsText($userAttributeName, $userAttributeValue, $condition, $context->key);

                return $this->evaluateSensitiveTextSliceEqualsAnyOf(
                    $text,
                    $condition[UserCondition::STRINGLIST_COMPARISON_VALUE] ?? null,
                    self::ensureConfigJsonSalt($context->setting[Setting::CONFIG_JSON_SALT]),
                    $contextSalt,
                    true,
                    UserComparator::SENSITIVE_TEXT_NOT_STARTS_WITH_ANY_OF === $comparator
                );

            case UserComparator::TEXT_ENDS_WITH_ANY_OF:
            case UserComparator::TEXT_NOT_ENDS_WITH_ANY_OF:
                $text = $this->getUserAttributeValueAsText($userAttributeName, $userAttributeValue, $condition, $context->key);

                return $this->evaluateTextSliceEqualsAnyOf(
                    $text,
                    $condition[UserCondition::STRINGLIST_COMPARISON_VALUE] ?? null,
                    false,
                    UserComparator::TEXT_NOT_ENDS_WITH_ANY_OF === $comparator
                );

            case UserComparator::SENSITIVE_TEXT_ENDS_WITH_ANY_OF:
            case UserComparator::SENSITIVE_TEXT_NOT_ENDS_WITH_ANY_OF:
                $text = $this->getUserAttributeValueAsText($userAttributeName, $userAttributeValue, $condition, $context->key);

                return $this->evaluateSensitiveTextSliceEqualsAnyOf(
                    $text,
                    $condition[UserCondition::STRINGLIST_COMPARISON_VALUE] ?? null,
                    self::ensureConfigJsonSalt($context->setting[Setting::CONFIG_JSON_SALT]),
                    $contextSalt,
                    false,
                    UserComparator::SENSITIVE_TEXT_NOT_ENDS_WITH_ANY_OF === $comparator
                );

            case UserComparator::TEXT_CONTAINS_ANY_OF:
            case UserComparator::TEXT_NOT_CONTAINS_ANY_OF:
                $text = $this->getUserAttributeValueAsText($userAttributeName, $userAttributeValue, $condition, $context->key);

                return $this->evaluateTextContainsAnyOf(
                    $text,
                    $condition[UserCondition::STRINGLIST_COMPARISON_VALUE] ?? null,
                    UserComparator::TEXT_NOT_CONTAINS_ANY_OF === $comparator
                );

            case UserComparator::SEMVER_IS_ONE_OF:
            case UserComparator::SEMVER_IS_NOT_ONE_OF:
                $versionOrError = $this->getUserAttributeValueAsSemVer($userAttributeName, $userAttributeValue, $condition, $context->key);

                return !is_string($versionOrError)
                    ? $this->evaluateSemVerIsOneOf(
                        $versionOrError,
                        $condition[UserCondition::STRINGLIST_COMPARISON_VALUE] ?? null,
                        UserComparator::SEMVER_IS_NOT_ONE_OF === $comparator
                    )
                    : $versionOrError;

            case UserComparator::SEMVER_LESS:
            case UserComparator::SEMVER_LESS_OR_EQUALS:
            case UserComparator::SEMVER_GREATER:
            case UserComparator::SEMVER_GREATER_OR_EQUALS:
                $versionOrError = $this->getUserAttributeValueAsSemVer($userAttributeName, $userAttributeValue, $condition, $context->key);

                return !is_string($versionOrError)
                    ? $this->evaluateSemVerRelation(
                        $versionOrError,
                        $comparator, // @phpstan-ignore-line
                        $condition[UserCondition::STRING_COMPARISON_VALUE] ?? null
                    )
                    : $versionOrError;

            case UserComparator::NUMBER_EQUALS:
            case UserComparator::NUMBER_NOT_EQUALS:
            case UserComparator::NUMBER_LESS:
            case UserComparator::NUMBER_LESS_OR_EQUALS:
            case UserComparator::NUMBER_GREATER:
            case UserComparator::NUMBER_GREATER_OR_EQUALS:
                $numberOrError = $this->getUserAttributeValueAsNumber($userAttributeName, $userAttributeValue, $condition, $context->key);

                return !is_string($numberOrError)
                    ? $this->evaluateNumberRelation(
                        $numberOrError,
                        $comparator, // @phpstan-ignore-line
                        $condition[UserCondition::NUMBER_COMPARISON_VALUE] ?? null
                    )
                    : $numberOrError;

            case UserComparator::DATETIME_BEFORE:
            case UserComparator::DATETIME_AFTER:
                $numberOrError = $this->getUserAttributeValueAsUnixTimeSeconds($userAttributeName, $userAttributeValue, $condition, $context->key);

                return !is_string($numberOrError)
                    ? $this->evaluateDateTimeRelation(
                        $numberOrError,
                        $condition[UserCondition::NUMBER_COMPARISON_VALUE] ?? null,
                        UserComparator::DATETIME_BEFORE === $comparator
                    )
                    : $numberOrError;

            case UserComparator::ARRAY_CONTAINS_ANY_OF:
            case UserComparator::ARRAY_NOT_CONTAINS_ANY_OF:
                $arrayOrError = $this->getUserAttributeValueAsStringArray($userAttributeName, $userAttributeValue, $condition, $context->key);

                return !is_string($arrayOrError)
                    ? $this->evaluateArrayContainsAnyOf(
                        $arrayOrError,
                        $condition[UserCondition::STRINGLIST_COMPARISON_VALUE] ?? null,
                        UserComparator::ARRAY_NOT_CONTAINS_ANY_OF === $comparator
                    )
                    : $arrayOrError;

            case UserComparator::SENSITIVE_ARRAY_CONTAINS_ANY_OF:
            case UserComparator::SENSITIVE_ARRAY_NOT_CONTAINS_ANY_OF:
                $arrayOrError = $this->getUserAttributeValueAsStringArray($userAttributeName, $userAttributeValue, $condition, $context->key);

                return !is_string($arrayOrError)
                    ? $this->evaluateSensitiveArrayContainsAnyOf(
                        $arrayOrError,
                        $condition[UserCondition::STRINGLIST_COMPARISON_VALUE] ?? null,
                        self::ensureConfigJsonSalt($context->setting[Setting::CONFIG_JSON_SALT]),
                        $contextSalt,
                        UserComparator::SENSITIVE_ARRAY_NOT_CONTAINS_ANY_OF === $comparator
                    )
                    : $arrayOrError;

            default:
                throw new UnexpectedValueException('Comparison operator is missing or invalid.');
        }
    }

    private static function evaluateTextEquals(string $text, mixed $comparisonValue, bool $negate): bool
    {
        self::ensureStringComparisonValue($comparisonValue);

        return ($text === $comparisonValue) !== $negate;
    }

    private static function evaluateSensitiveTextEquals(string $text, mixed $comparisonValue, string $configJsonSalt, string $contextSalt, bool $negate): bool
    {
        self::ensureStringComparisonValue($comparisonValue);

        $hash = self::hashComparisonValue($text, $configJsonSalt, $contextSalt);

        return ($hash === $comparisonValue) !== $negate;
    }

    private static function evaluateTextIsOneOf(string $text, mixed $comparisonValues, bool $negate): bool
    {
        self::ensureComparisonValues($comparisonValues);

        foreach ($comparisonValues as $comparisonValue) {
            if ($text === self::ensureStringComparisonValue($comparisonValue)) {
                return !$negate;
            }
        }

        return $negate;
    }

    private static function evaluateSensitiveTextIsOneOf(string $text, mixed $comparisonValues, string $configJsonSalt, string $contextSalt, bool $negate): bool
    {
        self::ensureComparisonValues($comparisonValues);

        $hash = self::hashComparisonValue($text, $configJsonSalt, $contextSalt);

        foreach ($comparisonValues as $comparisonValue) {
            if ($hash === self::ensureStringComparisonValue($comparisonValue)) {
                return !$negate;
            }
        }

        return $negate;
    }

    private static function evaluateTextSliceEqualsAnyOf(string $text, mixed $comparisonValues, bool $startsWith, bool $negate): bool
    {
        self::ensureComparisonValues($comparisonValues);

        foreach ($comparisonValues as $comparisonValue) {
            $item = self::ensureStringComparisonValue($comparisonValue);

            $success = $startsWith ? str_starts_with($text, $item) : str_ends_with($text, $item);

            if ($success) {
                return !$negate;
            }
        }

        return $negate;
    }

    private static function evaluateSensitiveTextSliceEqualsAnyOf(string $text, mixed $comparisonValues, string $configJsonSalt, string $contextSalt, bool $startsWith, bool $negate): bool
    {
        self::ensureComparisonValues($comparisonValues);

        $textLength = strlen($text);

        foreach ($comparisonValues as $comparisonValue) {
            $item = self::ensureStringComparisonValue($comparisonValue);

            $index = strpos($item, '_');

            if (false === $index
                || false === ($sliceLength = filter_var(substr($item, 0, $index), FILTER_VALIDATE_INT))
                || '' === ($hash2 = substr($item, $index + 1))) {
                self::ensureStringComparisonValue(null);

                break; // execution should never get here (this is just for keeping phpstan happy)
            }

            if ($textLength < $sliceLength) {
                continue;
            }

            $slice = $startsWith ? substr($text, 0, $sliceLength) : substr($text, $textLength - $sliceLength);

            $hash = self::hashComparisonValue($slice, $configJsonSalt, $contextSalt);
            if ($hash === $hash2) {
                return !$negate;
            }
        }

        return $negate;
    }

    private static function evaluateTextContainsAnyOf(string $text, mixed $comparisonValues, bool $negate): bool
    {
        self::ensureComparisonValues($comparisonValues);

        foreach ($comparisonValues as $comparisonValue) {
            if (false !== strpos($text, self::ensureStringComparisonValue($comparisonValue))) {
                return !$negate;
            }
        }

        return $negate;
    }

    private static function evaluateSemVerIsOneOf(Version $version, mixed $comparisonValues, bool $negate): bool
    {
        self::ensureComparisonValues($comparisonValues);

        $result = false;

        foreach ($comparisonValues as $comparisonValue) {
            self::ensureStringComparisonValue($comparisonValue);

            // NOTE: Previous versions of the evaluation algorithm ignore empty comparison values.
            // We keep this behavior for backward compatibility.
            if ('' === $comparisonValue) {
                continue;
            }

            $version2 = Version::parseOrNull(trim($comparisonValue));
            if (!$version2) {
                // NOTE: Previous versions of the evaluation algorithm ignored invalid comparison values.
                // We keep this behavior for backward compatibility.
                return false;
            }

            if (!$result && 0 === Version::compare($version, $version2)) {
                // NOTE: Previous versions of the evaluation algorithm require that
                // none of the comparison values are empty or invalid, that is, we can't stop when finding a match.
                // We keep this behavior for backward compatibility.
                $result = true;
            }
        }

        return $result !== $negate;
    }

    private static function evaluateSemVerRelation(Version $version, UserComparator $comparator, mixed $comparisonValue): bool
    {
        self::ensureStringComparisonValue($comparisonValue);

        $version2 = Version::parseOrNull(trim($comparisonValue));

        if (!$version2) {
            return false;
        }

        $comparisonResult = Version::compare($version, $version2);

        switch ($comparator) {
            case UserComparator::SEMVER_LESS: return $comparisonResult < 0;

            case UserComparator::SEMVER_LESS_OR_EQUALS: return $comparisonResult <= 0;

            case UserComparator::SEMVER_GREATER: return $comparisonResult > 0;

            case UserComparator::SEMVER_GREATER_OR_EQUALS: return $comparisonResult >= 0;

            default: throw new LogicException();
        }
    }

    private static function evaluateNumberRelation(float $number, UserComparator $comparator, mixed $comparisonValue): bool
    {
        $number2 = self::ensureNumberComparisonValue($comparisonValue);

        switch ($comparator) {
            case UserComparator::NUMBER_EQUALS: return $number === $number2;

            case UserComparator::NUMBER_NOT_EQUALS: return $number !== $number2;

            case UserComparator::NUMBER_LESS: return $number < $number2;

            case UserComparator::NUMBER_LESS_OR_EQUALS: return $number <= $number2;

            case UserComparator::NUMBER_GREATER: return $number > $number2;

            case UserComparator::NUMBER_GREATER_OR_EQUALS: return $number >= $number2;

            default: throw new LogicException();
        }
    }

    private static function evaluateDateTimeRelation(float $number, mixed $comparisonValue, bool $before): bool
    {
        $number2 = self::ensureNumberComparisonValue($comparisonValue);

        return $before ? $number < $number2 : $number > $number2;
    }

    /**
     * @param list<string> $array
     */
    private static function evaluateArrayContainsAnyOf(array $array, mixed $comparisonValues, bool $negate): bool
    {
        self::ensureComparisonValues($comparisonValues);

        foreach ($array as $text) {
            foreach ($comparisonValues as $comparisonValue) {
                if ($text === self::ensureStringComparisonValue($comparisonValue)) {
                    return !$negate;
                }
            }
        }

        return $negate;
    }

    /**
     * @param list<string> $array
     */
    private static function evaluateSensitiveArrayContainsAnyOf(array $array, mixed $comparisonValues, string $configJsonSalt, string $contextSalt, bool $negate): bool
    {
        self::ensureComparisonValues($comparisonValues);

        foreach ($array as $text) {
            $hash = self::hashComparisonValue($text, $configJsonSalt, $contextSalt);

            foreach ($comparisonValues as $comparisonValue) {
                if ($hash === self::ensureStringComparisonValue($comparisonValue)) {
                    return !$negate;
                }
            }
        }

        return $negate;
    }

    /**
     * @param array<string, mixed> $condition
     */
    private function evaluatePrerequisiteFlagCondition(array $condition, EvaluateContext $context): bool
    {
        $logBuilder = $context->logBuilder;
        $logBuilder?->appendPrerequisiteFlagCondition($condition, $context->settings);

        $prerequisiteFlagKey = $condition[PrerequisiteFlagCondition::PREREQUISITE_FLAG_KEY] ?? null;
        if (!is_string($prerequisiteFlagKey)) {
            throw new UnexpectedValueException('Prerequisite flag key is missing or invalid.');
        }

        $prerequisiteFlag = $context->settings[$prerequisiteFlagKey] ?? null;
        if (!is_array($prerequisiteFlag)) {
            throw new UnexpectedValueException('Prerequisite flag is missing or invalid.');
        }

        /** @var SettingType|stdClass $prerequisiteFlagType */
        $prerequisiteFlagType = Setting::getType($prerequisiteFlag);

        $comparisonValue = SettingValue::get($condition[PrerequisiteFlagCondition::COMPARISON_VALUE] ?? null, $prerequisiteFlagType, false);
        if (!isset($comparisonValue) && !($prerequisiteFlagType instanceof stdClass)) {
            $comparisonValue = SettingValue::infer($condition[PrerequisiteFlagCondition::COMPARISON_VALUE] ?? null);
            $comparisonValueFormatted = EvaluateLogBuilder::formatSettingValue($comparisonValue);

            throw new UnexpectedValueException("Type mismatch between comparison value '{$comparisonValueFormatted}' and prerequisite flag '{$prerequisiteFlagKey}'.");
        }

        $visitedFlags = &$context->getVisitedFlags();
        array_push($visitedFlags, $context->key);
        if (in_array($prerequisiteFlagKey, $visitedFlags, true)) {
            array_push($visitedFlags, $prerequisiteFlagKey);
            $dependencyCycle = Utils::formatStringList($visitedFlags, 0, null, ' -> ');

            throw new UnexpectedValueException("Circular dependency detected between the following depending flags: {$dependencyCycle}.");
        }

        $prerequisiteFlagContext = EvaluateContext::forPrerequisiteFlag($prerequisiteFlagKey, $prerequisiteFlag, $context);

        $logBuilder?->newLine('(')
            ->increaseIndent()
            ->newLine()->append("Evaluating prerequisite flag '{$prerequisiteFlagKey}':")
        ;

        $prerequisiteFlagEvaluateResult = $this->evaluateSetting($prerequisiteFlagContext);

        array_pop($visitedFlags);

        $prerequisiteFlagValue = SettingValue::get(
            $prerequisiteFlagEvaluateResult->selectedValue[SettingValueContainer::VALUE] ?? null,
            $prerequisiteFlagType
        );

        $comparator = PrerequisiteFlagComparator::tryFrom($condition[PrerequisiteFlagCondition::COMPARATOR] ?? null);

        switch ($comparator) {
            case PrerequisiteFlagComparator::EQUALS:
                $result = $prerequisiteFlagValue === $comparisonValue;

                break;

            case PrerequisiteFlagComparator::NOT_EQUALS:
                $result = $prerequisiteFlagValue !== $comparisonValue;

                break;

            default:
                throw new UnexpectedValueException('Comparison operator is missing or invalid.');
        }

        if ($logBuilder) {
            $prerequisiteFlagValueFormatted = EvaluateLogBuilder::formatSettingValue($prerequisiteFlagValue);
            $logBuilder->newLine()->append("Prerequisite flag evaluation result: '{$prerequisiteFlagValueFormatted}'.")
                ->newLine('Condition (')
                ->appendPrerequisiteFlagCondition($condition, $context->settings)
                ->append(') evaluates to ')->appendConditionResult($result)->append('.')
                ->decreaseIndent()
                ->newLine(')')
            ;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $condition
     */
    private function evaluateSegmentCondition(array $condition, EvaluateContext $context): bool|string
    {
        $segments = $context->setting[Setting::CONFIG_SEGMENTS];

        $logBuilder = $context->logBuilder;
        $logBuilder?->appendSegmentCondition($condition, $segments);

        if (!$context->user) {
            if (!$context->isMissingUserObjectLogged) {
                $this->logUserObjectIsMissing($context->key);
                $context->isMissingUserObjectLogged = true;
            }

            return self::MISSING_USER_OBJECT_ERROR;
        }

        $segments = Segment::ensureList($segments);

        $segmentIndex = $condition[SegmentCondition::SEGMENT_INDEX] ?? null;
        if (!is_int($segmentIndex) || $segmentIndex < 0 || count($segments) <= $segmentIndex) {
            throw new UnexpectedValueException('Segment reference is invalid.');
        }

        $segment = Segment::ensure($segments[$segmentIndex]);

        $segmentName = $segment[Segment::NAME] ?? null;
        if (!is_string($segmentName) || '' === $segmentName) {
            throw new UnexpectedValueException('Segment name is missing.');
        }

        $logBuilder?->newLine('(')
            ->increaseIndent()
            ->newLine("Evaluating segment '{$segmentName}':")
        ;

        $conditions = ConditionContainer::ensureList($segment[Segment::CONDITIONS] ?? []);

        $segmentResult = $this->evaluateConditions($conditions, Segment::conditionAccessor(), null, $segmentName, $context);
        $result = $segmentResult;

        if (!is_string($result)) {
            $comparator = SegmentComparator::tryFrom($condition[SegmentCondition::COMPARATOR] ?? null);

            switch ($comparator) {
                case SegmentComparator::IS_IN:
                    break;

                case SegmentComparator::IS_NOT_IN:
                    $result = !$result;

                    break;

                default:
                    throw new UnexpectedValueException('Comparison operator is missing or invalid.');
            }
        }

        if ($logBuilder) {
            $logBuilder->newLine('Segment evaluation result: ');

            if (!is_string($result)) {
                $comparatorText = EvaluateLogBuilder::formatSegmentComparator($segmentResult ? SegmentComparator::IS_IN : SegmentComparator::IS_NOT_IN);
                $logBuilder->append("User {$comparatorText}");
            } else {
                $logBuilder->append($result);
            }
            $logBuilder->append('.');

            $logBuilder->newLine('Condition (')->appendSegmentCondition($condition, $segments)->append(')');
            (!is_string($result)
              ? $logBuilder->append(' evaluates to ')->appendConditionResult($result)
              : $logBuilder->append(' failed to evaluate'))
                ->append('.')
            ;

            $logBuilder
                ->decreaseIndent()
                ->newLine(')')
            ;
        }

        return $result;
    }

    private static function ensureConfigJsonSalt(mixed $value): string
    {
        return is_string($value)
            ? $value
            : throw new UnexpectedValueException('Config JSON salt is missing or invalid.');
    }

    /**
     * @return list<mixed>
     */
    private static function ensureComparisonValues(mixed $comparisonValues): array
    {
        return array_is_list($comparisonValues)
            ? $comparisonValues
            : throw new UnexpectedValueException('Comparison value is missing or invalid.');
    }

    private static function ensureStringComparisonValue(mixed $comparisonValue): string
    {
        return is_string($comparisonValue)
            ? $comparisonValue
            : throw new UnexpectedValueException('Comparison value is missing or invalid.');
    }

    private static function ensureNumberComparisonValue(mixed $comparisonValue): float
    {
        return is_float($comparisonValue) || is_int($comparisonValue)
            ? (float) $comparisonValue
            : throw new UnexpectedValueException('Comparison value is missing or invalid.');
    }

    private static function hashComparisonValue(string $value, string $configJsonSalt, string $contextSalt): string
    {
        return hash('sha256', $value.$configJsonSalt.$contextSalt);
    }

    private static function userAttributeValueToString(mixed $attributeValue): string
    {
        if (is_string($attributeValue)) {
            return $attributeValue;
        }
        if (is_double($attributeValue) || is_int($attributeValue)) {
            return Utils::numberToString($attributeValue);
        }
        if ($attributeValue instanceof DateTimeInterface
            && is_double($unixTimeSeconds = Utils::dateTimeToUnixSeconds($attributeValue))) {
            return Utils::numberToString($unixTimeSeconds);
        }
        if (Utils::isStringList($attributeValue)
            && ($stringArrayJson = json_encode($attributeValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {
            return $stringArrayJson;
        }

        return Utils::getStringRepresentation($attributeValue);
    }

    /**
     * @param array<string, mixed> $condition
     */
    private function getUserAttributeValueAsText(string $attributeName, mixed $attributeValue, array $condition, string $key): string
    {
        if (is_string($attributeValue)) {
            return $attributeValue;
        }

        $text = self::userAttributeValueToString($attributeValue);
        $this->logUserObjectAttributeIsAutoConverted(EvaluateLogBuilder::formatUserCondition($condition), $key, $attributeName, $text);

        return $text;
    }

    /**
     * @param array<string, mixed> $condition
     */
    private function getUserAttributeValueAsSemVer(string $attributeName, mixed $attributeValue, array $condition, string $key): string|Version
    {
        if (is_string($attributeValue)) {
            $version = Version::parseOrNull(trim($attributeValue));
            if ($version) {
                return $version;
            }
        }

        $attributeValueFormatted = Utils::getStringRepresentation($attributeValue);
        return $this->handleInvalidUserAttribute($condition, $key, $attributeName, "'{$attributeValueFormatted}' is not a valid semantic version");
    }

    /**
     * @param array<string, mixed> $condition
     */
    private function getUserAttributeValueAsNumber(string $attributeName, mixed $attributeValue, array $condition, string $key): float|string
    {
        if (is_double($attributeValue) || is_int($attributeValue)) {
            return (float) $attributeValue;
        }
        if (is_string($attributeValue)) {
            $number = Utils::numberFromString(str_replace(',', '.', $attributeValue));
            if (is_double($number)) {
                return $number;
            }
        }

        $attributeValueFormatted = Utils::getStringRepresentation($attributeValue);
        return $this->handleInvalidUserAttribute($condition, $key, $attributeName, "'{$attributeValueFormatted}' is not a valid decimal number");
    }

    /**
     * @param array<string, mixed> $condition
     */
    private function getUserAttributeValueAsUnixTimeSeconds(string $attributeName, mixed $attributeValue, array $condition, string $key): float|string
    {
        if ($attributeValue instanceof DateTimeInterface) {
            $unixTimeSeconds = Utils::dateTimeToUnixSeconds($attributeValue);
            if (is_double($unixTimeSeconds)) {
                return $unixTimeSeconds;
            }
        } elseif (is_double($attributeValue) || is_int($attributeValue)) {
            return (float) $attributeValue;
        } elseif (is_string($attributeValue)) {
            $unixTimeSeconds = Utils::numberFromString(str_replace(',', '.', $attributeValue));
            if (is_double($unixTimeSeconds)) {
                return $unixTimeSeconds;
            }
        }

        $attributeValueFormatted = Utils::getStringRepresentation($attributeValue);
        return $this->handleInvalidUserAttribute($condition, $key, $attributeName, "'{$attributeValueFormatted}' is not a valid Unix timestamp (number of seconds elapsed since Unix epoch)");
    }

    /**
     * @param array<string, mixed> $condition
     *
     * @return list<string>
     */
    private function getUserAttributeValueAsStringArray(string $attributeName, mixed $attributeValue, array $condition, string $key): array|string
    {
        if (is_array($attributeValue)) {
            if (Utils::isStringList($attributeValue)) {
                return $attributeValue;
            }
        } elseif (is_string($attributeValue)) {
            $stringArray = json_decode($attributeValue, true);
            if (JSON_ERROR_NONE === json_last_error() && Utils::isStringList($stringArray)) {
                return $stringArray;
            }
        }

        $attributeValueFormatted = Utils::getStringRepresentation($attributeValue);
        return $this->handleInvalidUserAttribute($condition, $key, $attributeName, "'{$attributeValueFormatted}' is not a valid string array");
    }

    private function logUserObjectIsMissing(string $key): void
    {
        $this->logger->warning("Cannot evaluate targeting rules and % options for setting '{$key}' (User Object is missing). ".
            'You should pass a User Object to the evaluation methods like `getValue()` in order to make targeting work properly. '.
            'Read more: https://configcat.com/docs/advanced/user-object/', [
                'event_id' => 3001,
            ]);
    }

    private function logUserObjectAttributeIsMissingPercentage(string $key, string $attributeName): void
    {
        $this->logger->warning("Cannot evaluate % options for setting '{$key}' (the User.{$attributeName} attribute is missing). ".
            "You should set the User.{$attributeName} attribute in order to make targeting work properly. ".
            'Read more: https://configcat.com/docs/advanced/user-object/', [
                'event_id' => 3003,
            ]);
    }

    private function logUserObjectAttributeIsMissingCondition(string $condition, string $key, string $attributeName): void
    {
        $this->logger->warning("Cannot evaluate condition ({$condition}) for setting '{$key}' (the User.{$attributeName} attribute is missing). ".
            "You should set the User.{$attributeName} attribute in order to make targeting work properly. ".
            'Read more: https://configcat.com/docs/advanced/user-object/', [
                'event_id' => 3003,
            ]);
    }

    private function logUserObjectAttributeIsInvalid(string $condition, string $key, string $reason, string $attributeName): void
    {
        $this->logger->warning("Cannot evaluate condition ({$condition}) for setting '{$key}' ({$reason}). ".
            "Please check the User.{$attributeName} attribute and make sure that its value corresponds to the comparison operator.", [
                'event_id' => 3004,
            ]);
    }

    private function logUserObjectAttributeIsAutoConverted(string $condition, string $key, string $attributeName, string $attributeValue): void
    {
        $this->logger->warning("Evaluation of condition ({$condition}) for setting '{$key}' may not produce the expected result ".
            "(the User.{$attributeName} attribute is not a string value, thus it was automatically converted to the string value '{$attributeValue}'). ".
            'Please make sure that using a non-string value was intended.', [
                'event_id' => 3005,
            ]);
    }

    /**
     * @param array<string, mixed> $condition
     */
    private function handleInvalidUserAttribute(array $condition, string $key, string $attributeName, string $reason): string
    {
        $this->logUserObjectAttributeIsInvalid(EvaluateLogBuilder::formatUserCondition($condition), $key, $reason, $attributeName);

        return sprintf(self::INVALID_USER_ATTRIBUTE_ERROR, $attributeName, $reason);
    }

    private function checkDefaultValueTypeMismatch(mixed $returnValue, mixed $defaultValue, SettingType $settingType): void
    {
        if (!isset($defaultValue)) { // when default value is null, the type of return value can be of any allowed type
            return;
        }
        if (is_bool($returnValue)) {
            if (is_bool($defaultValue)) {
                return;
            }
        } elseif (is_string($returnValue)) {
            if (is_string($defaultValue)) {
                return;
            }
        } elseif (is_int($returnValue) || is_float($returnValue)) {
            if (is_int($defaultValue) || is_float($defaultValue)) {
                return;
            }
        }

        $settingTypeName = $settingType->name;
        $defaultValueType = gettype($defaultValue);

        $this->logger->warning("The type of a setting does not match the type of the specified default value ({$defaultValue}). ".
            "Setting's type was {$settingTypeName} but the default value's type was {$defaultValueType}. ".
            "Please make sure that using a default value not matching the setting's type was intended.", [
                'event_id' => 4002,
            ]);
    }
}
