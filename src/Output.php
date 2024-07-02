<?php

namespace LukeDavis\GcpApiGatewaySpec;

use Symfony\Component\Yaml\Yaml;

class Output
{
    /**
     * Output spec destination path.
     */
    protected string $output_path;

    public function __construct(string $output_path)
    {
        $this->output_path = $output_path;
    }

    /**
     * Saves the generated spec file.
     *
     * @param array<string, mixed> $outputSpec The generated spec file
     */
    public function save(array $outputSpec): void
    {
        // Resolve the output path relative to the current working directory
        $outputPath = $this->resolveOutputPath($this->output_path);

        // Create the output directory if it doesn't exist
        $outputDir = dirname($outputPath);

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Save the output spec file
        file_put_contents($outputPath, Yaml::dump($outputSpec, 10, 2));
    }

    /**
     * Resolve the output path relative to the current working directory.
     */
    protected function resolveOutputPath(string $output): string
    {
        // Check if the path is already absolute
        if (realpath($output) !== false) {
            return realpath($output);
        }

        // If the path is relative, prepend the current working directory
        return getcwd().DIRECTORY_SEPARATOR.$output;
    }
}
