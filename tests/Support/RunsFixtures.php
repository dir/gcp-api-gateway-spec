<?php

namespace LukeDavis\GcpApiGatewaySpec\Tests\Support;

use LukeDavis\GcpApiGatewaySpec\Generator;

trait RunsFixtures
{
    protected function fixtureDir(string $case): string
    {
        return dirname(__DIR__).'/fixtures/cases/'.$case;
    }

    /**
     * Reads the optional options.json for a fixture case.
     *
     * @return array<string, mixed>
     */
    protected function fixtureOptions(string $case): array
    {
        $optionsFile = $this->fixtureDir($case).'/options.json';

        if (!is_file($optionsFile)) {
            return [];
        }

        return json_decode((string) file_get_contents($optionsFile), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Runs the generator on a fixture case from an isolated temporary
     * working directory and returns the generated YAML.
     */
    protected function generateFixture(string $case): string
    {
        $dir = $this->fixtureDir($case);
        $options = $this->fixtureOptions($case);

        $tmp = $this->makeTempDir();
        $cwd = (string) getcwd();
        chdir($tmp);

        try {
            $generator = new Generator(
                inputSpec: $dir.'/input.yaml',
                outputPath: 'output.yaml',
                config: $dir.'/config.yaml',
                host: $options['host'] ?? null,
                backendHost: $options['backend'] ?? null,
                preserveResponses: $options['preserveResponses'] ?? false,
            );

            $generator->validate();
            $generator->generate();
            $saved = $generator->save();

            return (string) file_get_contents($saved);
        } finally {
            chdir($cwd);
            @unlink($tmp.'/output.yaml');
            @rmdir($tmp);
        }
    }

    protected function makeTempDir(): string
    {
        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gcp-api-gateway-spec-tests-'.bin2hex(random_bytes(6));
        mkdir($tmp, 0755, true);

        return $tmp;
    }
}
