<?php

namespace LukeDavis\GcpApiGatewaySpec\Tests;

use LukeDavis\GcpApiGatewaySpec\Tests\Support\RunsFixtures;
use LukeDavis\GcpApiGatewaySpec\Tests\Support\ValidatesSwaggerSchema;
use PHPUnit\Framework\TestCase;

/**
 * Every fixture case must generate output that passes validation against
 * the official Swagger 2.0 JSON schema — the same validation gcloud runs
 * on `api-gateway api-configs create`.
 */
final class SwaggerSchemaValidationTest extends TestCase
{
    use RunsFixtures;
    use ValidatesSwaggerSchema;

    /**
     * @dataProvider caseProvider
     */
    public function testGeneratedSpecValidates(string $case): void
    {
        $this->assertValidSwaggerSpec($this->generateFixture($case), "Fixture: {$case}");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function caseProvider(): iterable
    {
        $dirs = glob(__DIR__.'/fixtures/cases/*', GLOB_ONLYDIR) ?: [];

        foreach ($dirs as $dir) {
            yield basename($dir) => [basename($dir)];
        }
    }
}
