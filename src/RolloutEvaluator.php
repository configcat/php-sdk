<?php

namespace ConfigCat;

/**
 * Class RolloutEvaluator
 * @package ConfigCat
 */
final class RolloutEvaluator
{
    /**
     * Evaluates a requested value from the configuration by the specified roll out rules.
     *
     * @param string $key The key of the desired value.
     * @param array $json The decoded JSON configuration.
     * @param User|null $user Optional. The user to identify the caller.
     * @return mixed The evaluated configuration value.
     */
    public static function evaluate($key, array $json, User $user = null)
    {
        if(is_null($user)) {
            return $json['Value'];
        }

        if(isset($json['RolloutRules']) && !empty($json['RolloutRules'])) {
            foreach ($json['RolloutRules'] as $rule) {
                $comparisonAttribute = $rule['ComparisonAttribute'];
                $comparisonValue = $rule['ComparisonValue'];
                $comparator = $rule['Comparator'];
                $value = $rule['Value'];
                $userValue = $user->getAttribute($comparisonAttribute);

                if(empty($comparisonValue) || empty($userValue)) {
                    continue;
                }

                switch ($comparator) {
                    case 0:
                        $split = Utils::split_trim($comparisonValue);
                        if(in_array($userValue, $split, true)) {
                            return $value;
                        }
                        break;
                    case 1:
                        $split = Utils::split_trim($comparisonValue);
                        if(!in_array($userValue, $split, true)) {
                            return $value;
                        }
                        break;
                    case 2:
                        if(Utils::str_contains($userValue, $comparisonValue)) {
                            return $value;
                        }
                        break;
                    case 3:
                        if(!Utils::str_contains($userValue, $comparisonValue)) {
                            return $value;
                        }
                        break;
                }
            }
        }

        if(isset($json['RolloutPercentageItems']) && !empty($json['RolloutPercentageItems'])) {
            $hashCandidate = $key.$user->getIdentifier();
            $stringHash = substr(sha1($hashCandidate), 0, 7);
            $intHash = intval($stringHash, 16);
            $scale = $intHash % 100;

            $bucket = 0;
            foreach ($json['RolloutPercentageItems'] as $rule) {
                $bucket += $rule['Percentage'];
                if($scale < $bucket) {
                    return $rule['Value'];
                }
            }
        }

        return $json['Value'];
    }
}