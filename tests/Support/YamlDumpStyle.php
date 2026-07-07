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
     * Parses YAML preserving the map/sequence distinction (maps become
     * stdClass), so that semantic comparison still catches an empty schema
     * degrading to an empty sequence.
     */
    public static function parseSemantic(string $yaml): mixed
    {
        return Yaml::parse($yaml, Yaml::PARSE_OBJECT_FOR_MAP);
    }
}
