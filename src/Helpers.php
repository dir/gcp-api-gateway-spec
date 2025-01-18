<?php

namespace LukeDavis\GcpApiGatewaySpec;

class Helpers
{
    /**
     * Merges two arrays recursively, with the second array taking precedence.
     *
     * @param array<string, mixed> $array1 The first array
     * @param array<string, mixed> $array2 The second array
     *
     * @return array<string, mixed> The merged array
     */
    public static function array_merge_overwrite(array &$array1, array &$array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => &$value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                // If both are arrays, recursively merge
                $merged[$key] = self::array_merge_overwrite($merged[$key], $value);
            } else {
                // Otherwise, replace the value
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}