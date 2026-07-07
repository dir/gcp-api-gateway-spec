<?php

namespace LukeDavis\GcpApiGatewaySpec\Tests\Support;

use Symfony\Component\Yaml\Yaml;

/**
 * Golden files are byte-compared only when the installed symfony/yaml
 * produces the same dump style they were generated with (>= 6.3 for
 * DUMP_NUMERIC_KEY_AS_STRING, < 8.0 for expanded nested sequences).
 * On other versions the comparison falls back to semantic equality,
 * which still distinguishes empty maps from empty sequences.
 */
final class YamlDumpStyle
{
    public static function matchesGoldens(): bool
    {
        return \defined(Yaml::class.'::DUMP_NUMERIC_KEY_AS_STRING')
            && Yaml::dump([['a' => 1]], 2, 2) === "-\n  a: 1\n";
    }

    /**
     * Canonicalizes YAML for strict semantic comparison: parsing with
     * PARSE_OBJECT_FOR_MAP preserves the map/sequence distinction (an empty
     * schema degrading to an empty sequence stays detectable), and the JSON
     * encoding keeps scalar types strict ('1.0' vs 1.0, 'true' vs true),
     * unlike assertEquals over object trees.
     */
    public static function canonicalize(string $yaml): string
    {
        return json_encode(
            Yaml::parse($yaml, Yaml::PARSE_OBJECT_FOR_MAP),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION
        );
    }
}
