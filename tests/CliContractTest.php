<?php

namespace LukeDavis\GcpApiGatewaySpec\Tests;

use LukeDavis\GcpApiGatewaySpec\Tests\Support\RunsFixtures;
use PHPUnit\Framework\TestCase;

/**
 * Freezes the CLI contract: command name, arguments, options, exit codes
 * and messages behave as they did in v2.0.3.
 */
final class CliContractTest extends TestCase
{
    use RunsFixtures;

    public function testGenerateSucceedsAndMatchesGoldenOutput(): void
    {
        $case = $this->fixtureDir('basic');
        $tmp = $this->makeTempDir();

        try {
            [$exitCode, $output] = $this->runCli(sprintf(
                'generate %s --config=%s --output=out.yaml --host=api.example.com --backend=https://backend.example.com',
                escapeshellarg($case.'/input.yaml'),
                escapeshellarg($case.'/config.yaml')
            ), $tmp);

            self::assertSame(0, $exitCode, $output);
            self::assertStringContainsString('successfully converted', $output);
            self::assertFileExists($tmp.'/out.yaml');
            self::assertSame(
                (string) file_get_contents($case.'/expected.yaml'),
                (string) file_get_contents($tmp.'/out.yaml'),
                'CLI-generated output differs from the golden output for the same fixture.'
            );
        } finally {
            @unlink($tmp.'/out.yaml');
            @rmdir($tmp);
        }
    }

    public function testMissingInputSpecFails(): void
    {
        [$exitCode, $output] = $this->runCli('generate does-not-exist.yaml --config=also-missing.yaml');

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Input API spec file not found', $output);
    }

    public function testMissingConfigFails(): void
    {
        $case = $this->fixtureDir('basic');

        [$exitCode, $output] = $this->runCli(sprintf(
            'generate %s --config=does-not-exist.yaml',
            escapeshellarg($case.'/input.yaml')
        ));

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Config file not found', $output);
    }

    public function testInvalidSwaggerVersionFails(): void
    {
        $tmp = $this->makeTempDir();

        try {
            file_put_contents($tmp.'/openapi3.yaml', "openapi: 3.0.0\npaths: {}\n");

            [$exitCode, $output] = $this->runCli(sprintf(
                'generate %s --config=%s',
                escapeshellarg($tmp.'/openapi3.yaml'),
                escapeshellarg($this->fixtureDir('basic').'/config.yaml')
            ), $tmp);

            self::assertSame(1, $exitCode);
            self::assertStringContainsString('not a valid Swagger 2.0 spec file', $output);
        } finally {
            @unlink($tmp.'/openapi3.yaml');
            @rmdir($tmp);
        }
    }

    /**
     * @return array{int, string}
     */
    private function runCli(string $args, ?string $cwd = null): array
    {
        $command = sprintf(
            'cd %s && %s %s %s 2>&1',
            escapeshellarg($cwd ?? sys_get_temp_dir()),
            escapeshellarg(PHP_BINARY),
            escapeshellarg(dirname(__DIR__).'/bin/gcp-api-gateway-spec'),
            $args
        );

        exec($command, $lines, $exitCode);

        return [$exitCode, implode("\n", $lines)];
    }
}
