<?php

namespace LukeDavis\GcpApiGatewaySpec\Tests;

use LukeDavis\GcpApiGatewaySpec\Tests\Support\RunsFixtures;
use PHPUnit\Framework\TestCase;

/**
 * Characterization tests: each fixture case under tests/fixtures/cases is
 * run through the generator and byte-compared against its committed
 * expected.yaml. Any change to generated output for these inputs is a
 * backwards-compatibility break unless the golden file is deliberately
 * regenerated (UPDATE_GOLDENS=1 vendor/bin/phpunit).
 */
final class GoldenOutputTest extends TestCase
{
    use RunsFixtures;

    /**
     * @dataProvider caseProvider
     */
    public function testOutputMatchesGolden(string $case): void
    {
        $generated = $this->generateFixture($case);
        $expectedFile = $this->fixtureDir($case).'/expected.yaml';

        if (getenv('UPDATE_GOLDENS') === '1') {
            file_put_contents($expectedFile, $generated);
            $this->addToAssertionCount(1);

            return;
        }

        self::assertFileExists($expectedFile);
        self::assertSame(
            (string) file_get_contents($expectedFile),
            $generated,
            "Generated output for fixture '{$case}' differs from its golden expected.yaml.\n"
            .'If this change is intentional, regenerate goldens with: UPDATE_GOLDENS=1 vendor/bin/phpunit'
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function caseProvider(): iterable
    {
        $dirs = glob(dirname(__DIR__).'/tests/fixtures/cases/*', GLOB_ONLYDIR) ?: [];

        foreach ($dirs as $dir) {
            yield basename($dir) => [basename($dir)];
        }
    }
}
